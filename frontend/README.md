# Stoic OS Frontend

Vue 3 + Vue Router + Pinia frontend for You OS, styled with Tailwind CSS.

## Run locally

1. Install dependencies:

```sh
npm install
```

2. Optional: create a `.env` file in `/frontend`:

```env
VITE_API_BASE_URL=http://127.0.0.1:8081
```

If omitted, the app defaults to `http://127.0.0.1:8081`.

3. Start development server:

```sh
npm run dev
```

4. Build production bundle:

```sh
npm run build
```

## Expected backend endpoints

The frontend currently expects these API routes:

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/user`
- `POST /api/v1/weekly-review/generate`
- `GET /api/v1/weekly-reviews`
- `GET /api/v1/weekly-reviews/{id}`
- `GET /api/v1/doctrine`
- `PUT /api/v1/doctrine`
- `POST /api/v1/checkin`
- `GET /api/v1/checkin/today`
- `GET /api/v1/checkins`
- `POST /api/v1/decisions`
- `GET /api/v1/decisions`
- `GET /api/v1/decisions/{id}`
- `PATCH /api/v1/decisions/{id}/outcome`
- `POST /api/v1/discipline-log`
- `GET /api/v1/discipline-log`
- `GET /api/v1/discipline-log/streak`

## Error normalization

Axios response interception normalizes API errors to:

```json
{
  "error": "error_code",
  "message": "Human readable message",
  "details": {}
}
```
