<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Response;
use App\Repositories\ProfileRepository;
use App\Services\ProfileService;
use Exception;

final class ProfileController
{
    private ProfileService $profileService;

    public function __construct()
    {
        $this->profileService = new ProfileService(new ProfileRepository());
    }

    public function create()
    {
        try {
            $name = $_POST['name'] ?: \json_decode(\file_get_contents('php://input'))->name;

            // Validate name
            if (empty($name ?? null)) {
                Response::error('Missing or empty name', 400)->send();
                return;
            }

            // Validate type
            if (!\is_string($name)) {
                Response::error('Invalid type', 422)->send();
                return;
            }

            // Create or get profile
            $result = $this->profileService->createOrGetProfile($name);
            /**
             * @var \App\Models\Profile */
            $profile = $result['profile'];
            $isNew = $result['isNew'];
            $message = $result['message'];

            $statusCode = $isNew ? 201 : 200;
            $response = Response::success(
                $profile->toArray(),
                $statusCode,
                $message
            );
            $response->send();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function getById(string $id): void
    {
        try {
            $profile = $this->profileService->getProfileById($id);

            if ($profile === null) {
                Response::error('Profile not found', 404)->send();
                return;
            }

            Response::success([$profile->toArray()])->send();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function getAll(): void
    {
        try {
            $filters = [];

            // Basic filters
            if (isset($_GET['gender'])) {
                $filters['gender'] = (string)$_GET['gender'];
            }

            if (isset($_GET['country_id'])) {
                $filters['country_id'] = (string)$_GET['country_id'];
            }

            if (isset($_GET['age_group'])) {
                $filters['age_group'] = (string)$_GET['age_group'];
            }

            // Numeric filters
            if (isset($_GET['min_age'])) {
                $minAge = \filter_var($_GET['min_age'], FILTER_VALIDATE_INT);
                if ($minAge !== false) {
                    $filters['min_age'] = $minAge;
                } else {
                    Response::error('Invalid query parameters', 400)->send();
                    return;
                }
            }

            if (isset($_GET['max_age'])) {
                $maxAge = \filter_var($_GET['max_age'], FILTER_VALIDATE_INT);
                if ($maxAge !== false) {
                    $filters['max_age'] = $maxAge;
                } else {
                    Response::error('Invalid query parameters', 400)->send();
                    return;
                }
            }

            if (isset($_GET['min_gender_probability'])) {
                $minGenderProb = \filter_var($_GET['min_gender_probability'], FILTER_VALIDATE_FLOAT);
                if ($minGenderProb !== false) {
                    $filters['min_gender_probability'] = $minGenderProb;
                } else {
                    Response::error('Invalid query parameters', 400)->send();
                    return;
                }
            }

            if (isset($_GET['min_country_probability'])) {
                $minCountryProb = \filter_var($_GET['min_country_probability'], FILTER_VALIDATE_FLOAT);
                if ($minCountryProb !== false) {
                    $filters['min_country_probability'] = $minCountryProb;
                } else {
                    Response::error('Invalid query parameters', 400)->send();
                    return;
                }
            }

            // Pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            // Validate pagination
            if ($page < 1 || $limit < 1 || $limit > 50) {
                Response::error('Invalid query parameters', 400)->send();
                return;
            }

            // Sorting
            $sortBy = $_GET['sort_by'] ?? 'created_at';
            $order = $_GET['order'] ?? 'desc';

            $result = $this->profileService->getProfilesWithPagination(
                $filters,
                $sortBy,
                $order,
                $page,
                $limit
            );

            $data = \array_map(fn($p) => $p->toArray(), $result['profiles']);

            Response::success($data, 200, null, $result['page'], $result['limit'], $result['total'])->send();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function export(): void
    {
        try {
            $filters = [];
            // Reuse filtering logic (DRY later if needed)
            if (isset($_GET['gender'])) $filters['gender'] = (string)$_GET['gender'];
            if (isset($_GET['country_id'])) $filters['country_id'] = (string)$_GET['country_id'];
            if (isset($_GET['age_group'])) $filters['age_group'] = (string)$_GET['age_group'];
            
            $sortBy = $_GET['sort_by'] ?? 'created_at';
            $order = $_GET['order'] ?? 'desc';

            // Get all matching profiles without pagination limit for export (or a very high one)
            $result = $this->profileService->getProfilesWithPagination(
                $filters,
                $sortBy,
                $order,
                1,
                1000 // High limit for export
            );

            $profiles = $result['profiles'];
            
            $filename = "profiles_" . date('Y-m-d_H-i-s') . ".csv";
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // CSV columns (in order): id, name, gender, gender_probability, age, age_group, country_id, country_name, country_probability, created_at
            fputcsv($output, [
                'id', 'name', 'gender', 'gender_probability', 'age', 'age_group', 
                'country_id', 'country_name', 'country_probability', 'created_at'
            ]);

            foreach ($profiles as $profile) {
                $data = $profile->toArray();
                fputcsv($output, [
                    $data['id'],
                    $data['name'],
                    $data['gender'],
                    $data['gender_probability'],
                    $data['age'],
                    $data['age_group'],
                    $data['country_id'],
                    $data['country_name'],
                    $data['country_probability'],
                    $data['created_at']
                ]);
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function delete(string $id): void
    {
        try {
            $deleted = $this->profileService->deleteProfile($id);

            if (!$deleted) {
                Response::error('Profile not found', 404)->send();
                return;
            }

            \http_response_code(204);
            exit;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function search(): void
    {
        try {
            // Get query parameter
            $query = $_GET['q'] ?? null;

            if (empty($query)) {
                Response::error('Missing or empty parameter', 400)->send();
                return;
            }

            // Pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            // Validate pagination
            if ($page < 1 || $limit < 1 || $limit > 50) {
                Response::error('Invalid query parameters', 400)->send();
                return;
            }

            // Search profiles
            $result = $this->profileService->searchProfiles($query, $page, $limit);

            $data = \array_map(fn($p) => $p->toArray(), $result['profiles']);

            Response::success($data, 200, null, $result['page'], $result['limit'], $result['total'])->send();
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (\str_contains($message, 'Unable to interpret query')) {
                Response::error('Unable to interpret query', 400)->send();
            } elseif (\str_contains($message, 'not found')) {
                // Handle country not found and other lookup errors
                Response::error($message, 400)->send();
            } else {
                $this->handleException($e);
            }
        }
    }

    private function handleException(Exception $e): void
    {
        $message = $e->getMessage();

        if (\str_contains($message, 'returned an invalid response')) {
            Response::error($message, 502)->send();
        } else {
            Response::error('Internal server error', 500)->send();
        }
    }
}
