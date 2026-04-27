# HNG-14 Stage 2: Intelligence Query Engine

A RESTful API for querying demographic profiles with advanced filtering, sorting, pagination, and natural language search.

## Features

- 5 API endpoints (Create, Read, Update, Delete, Search)
- Advanced filtering (gender, country, age group, age range, probability thresholds)
- Sorting & pagination with configurable limits
- Natural language search (rule-based pattern matching)
- UUID v7 primary keys
- MySQL database with optimized indexes

## Quick Start

### Installation

```bash
git clone https://github.com/pspepp3r/hng-14-task-2.git
cd hng-14-task-2
composer install
composer migrate
composer migrate seed data/seed_profiles.json
composer start --timeout=0
```

### API Base URL

```txt
http://localhost:8000/api
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/profiles` | Create profile by name |
| GET | `/profiles` | Get profiles with filters, sorting, pagination |
| GET | `/profiles/{id}` | Get single profile |
| DELETE | `/profiles/{id}` | Delete profile |
| GET | `/profiles/search` | Search via natural language query |

### GET /api/profiles (Query Parameters)

```txt
gender                 - male, female
country_id             - ISO code (NG, US, etc)
age_group              - child, teenager, adult, senior
min_age                - integer (min age)
max_age                - integer (max age)
min_gender_probability - float 0-1
min_country_probability- float 0-1
sort_by                - age, created_at, gender_probability
order                  - asc, desc
page                   - integer (default: 1)
limit                  - integer (default: 10, max: 50)
```

**Example:**

```txt
GET /api/profiles?gender=male&country_id=NG&min_age=25&sort_by=age&page=1&limit=10
```

### GET /api/profiles/search (Natural Language)

**Query:**

```txt
q=young males from nigeria
q=females above 30
q=adult males from kenya
```

**Response:**

```json
{
  "status": "success",
  "page": 1,
  "limit": 10,
  "total": 156,
  "data": [...]
}
```

## Natural Language Parser

Extracts demographics from plain English queries using regex patterns.

**Supported Keywords:**

- Gender: male, female, man, woman
- Age Groups: child, teenager, adult, senior
- Age Descriptors: young (16-24), old (50+), above/below/ages X-Y
- Countries: via REST Countries API + database fallback

**Limitations:**

- No complex logic operators (AND, OR, NOT)
- No fuzzy matching for typos
- First match wins for conflicting criteria

## Database Seeding

```bash
# Seed from JSON
php bin/migrate.php seed data/profiles.json

# Clear and reseed
php bin/migrate.php reseed data/profiles.json

# Clear all
php bin/migrate.php truncate
```

**JSON Format:**

```json
[
  {
    "name": "Emmanuel",
    "gender": "male",
    "gender_probability": 0.99,
    "age": 34,
    "age_group": "adult",
    "country_id": "NG",
    "country_name": "Nigeria",
    "country_probability": 0.85
  }
]
```

## Error Responses

```json
{
  "status": "error",
  "message": "Error description"
}
```

**HTTP Status Codes:**

- 200: Success
- 201: Created
- 204: Deleted
- 400: Bad Request
- 404: Not Found
- 422: Invalid Data
- 500: Server Error
