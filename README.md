# Insighta Labs+ Backend

A secure Profile Intelligence System with GitHub OAuth + PKCE, RBAC, and advanced demographic querying.

## System Architecture

The system is built with a decoupled architecture:
- **Core:** PHP 8.3 with Vanilla PHP (No heavy frameworks for maximum performance).
- **Authentication:** GitHub OAuth with PKCE for secure cross-interface sessions.
- **Session Management:** JWT (Access + Refresh tokens) for stateless authentication.
- **Database:** MySQL with UUID v7 primary keys and optimized indexing.
- **Security:** CSRF protection, HTTP-only cookies, Rate limiting, and Role-Based Access Control (RBAC).

## Authentication Flow (GitHub OAuth + PKCE)

1. **Initiation:** The client (CLI or Web) redirects the user to GitHub's Authorization endpoint.
2. **PKCE (CLI):** The CLI generates a `code_verifier` and `code_challenge`.
3. **Callback:** GitHub redirects back to the backend (Web) or local server (CLI) with an authorization `code`.
4. **Exchange:** The backend exchanges the `code` for a GitHub access token.
5. **Identification:** The backend retrieves the user's GitHub profile and identifies or creates the user in the local database.
6. **Token Issuance:** The backend issues two JWTs:
   - **Access Token:** 3-minute expiry (stored in HTTP-only cookie or memory).
   - **Refresh Token:** 5-minute expiry (stored in HTTP-only cookie or secure local storage).

## Token Handling Approach

- **Short-lived Access Tokens:** Minimize the window of risk if a token is intercepted.
- **Refresh Flow:** When an access token expires, the client calls `/auth/refresh` with the refresh token to receive a new pair.
- **Invalidation:** The `/auth/logout` endpoint clears cookies and invalidates the session client-side.

## Role Enforcement Logic

The system enforces two roles:
- **Admin:** Full access to all endpoints (Create, Read, Delete, Search, Export).
- **Analyst:** Read-only access (Read, Search, Export).
- **Implementation:** A dedicated `RbacMiddleware` checks the `role` claim in the JWT before allowing access to mutation endpoints (`POST`, `DELETE`).

## Natural Language Parsing

Demographics are extracted from plain English queries using regex-based pattern matching:
- **Gender:** male, female, etc.
- **Age Groups:** child, teenager, adult, senior.
- **Countries:** ISO codes and full names matched against database lookups.

## API Endpoints

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/auth/github` | Initiate GitHub OAuth | Public |
| GET | `/auth/github/callback` | OAuth Callback | Public |
| POST | `/auth/refresh` | Refresh JWT tokens | Public |
| POST | `/auth/logout` | Logout | Public |
| GET | `/api/profiles` | List profiles | Analyst |
| POST | `/api/profiles` | Create profile | Admin |
| GET | `/api/profiles/{id}`| Get single profile | Analyst |
| DELETE | `/api/profiles/{id}`| Delete profile | Admin |
| GET | `/api/profiles/search`| NL Search | Analyst |
| GET | `/api/profiles/export`| CSV Export | Analyst |

**Note:** All `/api/*` requests require `X-API-Version: 1` header and a valid Bearer token.
