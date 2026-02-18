# You OS

Monorepo bootstrap with:
- `backend/` Laravel API
- `frontend/` Vue 3 SPA

## Run Backend

```bash
cd backend
php artisan serve
```

## Run Frontend

```bash
cd frontend
npm run dev
```

## Required Environment Variables

Set these in `backend/.env`:

```env
OPENROUTER_API_KEY=your_openrouter_api_key_here
```
