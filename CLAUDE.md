# ASRLM Portal — Full Stack Documentation

**Assam State Rural Livelihoods Mission (ASRLM) Management Information System**

A multi-role web portal for managing rural livelihoods data, financial inclusion (member & SHG loans), HR operations, MIS hierarchy, and block cadre monitoring across Assam.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Tech Stack](#2-tech-stack)
3. [Repository Structure](#3-repository-structure)
4. [Backend — Laravel API](#4-backend--laravel-api)
   - [Prerequisites](#41-prerequisites)
   - [Installation](#42-installation)
   - [Environment Configuration](#43-environment-configuration)
   - [Database Setup](#44-database-setup)
   - [Running the Server](#45-running-the-server)
   - [API Reference](#46-api-reference)
   - [Models & Database Schema](#47-models--database-schema)
   - [Authentication](#48-authentication)
5. [Frontend — React App](#5-frontend--react-app)
   - [Prerequisites](#51-prerequisites)
   - [Installation](#52-installation)
   - [Environment Configuration](#53-environment-configuration)
   - [Running the Dev Server](#54-running-the-dev-server)
   - [Building for Production](#55-building-for-production)
   - [Pages & Routes](#56-pages--routes)
6. [User Roles](#6-user-roles)
7. [Key Features](#7-key-features)
8. [Project Architecture](#8-project-architecture)

---

## 1. Project Overview

The ASRLM Portal is a full-stack web application built for Assam State Rural Livelihoods Mission field operations. It provides:

- **Multi-tier user access** — State Admin, District Admin, Block Admin, and Block Cadre roles with granular permission boundaries
- **Financial Inclusion** — Member loan and SHG loan lifecycle management with EMI tracking, NPA detection, and bank reconciliation via CSV upload
- **HR Management** — Employee records, designations, attendance, and payroll processing
- **MIS Hierarchy** — District → Block → GP → Village → SHG → Member data browsing and editing
- **Block Cadre Mobile UI** — A dedicated mobile-first interface for field cadre workers to monitor and create loans
- **Self-Registration** — Block Cadre, Block Admin, and District Admin can self-register with Aadhaar + DOB; accounts are activated by an administrator
- **Forgot Password** — Identity verified via email/mobile + Aadhaar number before allowing password reset (no email server required)

---

## 2. Tech Stack

| Layer | Technology | Version |
|---|---|---|
| **Backend API** | Laravel | ^13.8 |
| **PHP** | PHP | ^8.3 |
| **HTTP Server** | Laravel Octane + RoadRunner | ^2.17 / 2.6.0 |
| **API Auth** | Laravel Sanctum (token-based) | ^4.3 |
| **Database** | MySQL | 8.x |
| **IFSC Lookup** | razorpay/ifsc | ^0.2.9 |
| **Frontend** | React | ^19.2.6 |
| **Routing** | React Router DOM | ^7.17.0 |
| **Styling** | Tailwind CSS | ^3.4.19 |
| **Build Tool** | Vite | ^8.0.12 |

---

## 3. Repository Structure

```
asrlm-portal/
├── apiasrlm/                  # Laravel Backend
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Auth/          # Login, Register, ForgotPassword, SetupPassword
│   │   │   ├── Financial/     # Loans, Payments, Banks, Cadres
│   │   │   ├── HR/            # Employees, Designations, Attendance, Payroll
│   │   │   ├── MIS/           # MIS hierarchy + Mapping
│   │   │   └── Public/        # Unauthenticated geo endpoints
│   │   └── Models/            # Eloquent models
│   ├── database/migrations/   # All DB migrations (chronological)
│   ├── routes/
│   │   └── api.php            # All API route definitions
│   ├── rr.exe                 # RoadRunner binary (Windows)
│   └── .env                   # Environment config (not committed)
│
└── my-react-app/              # React Frontend
    ├── src/
    │   ├── api/               # API client modules (auth, financial, hr, mis, etc.)
    │   ├── components/        # Shared UI + layout components
    │   ├── context/           # AuthContext
    │   ├── hooks/             # useAuth hook
    │   ├── pages/             # All page components by role/module
    │   │   ├── block/         # Block Admin pages (Cadre management)
    │   │   ├── cadre/         # Block Cadre mobile pages
    │   │   ├── state/         # State/District Admin pages (HR, Finance, etc.)
    │   │   └── users/         # User management pages
    │   └── App.jsx            # Root router
    └── vite.config.js
```

---

## 4. Backend — Laravel API

### 4.1 Prerequisites

- PHP 8.3+
- Composer
- MySQL 8.x
- RoadRunner binary (`rr.exe` for Windows, `rr` for Linux — already included in project root)

### 4.2 Installation

```bash
cd apiasrlm
composer install
cp .env.example .env
php artisan key:generate
```

### 4.3 Environment Configuration

Edit `.env` with your local values:

```env
APP_NAME="ASRLM Portal"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apiasrlm
DB_USERNAME=root
DB_PASSWORD=your_password

SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

OCTANE_SERVER=roadrunner
```

### 4.4 Database Setup

```bash
# Create the database in MySQL first, then run all migrations
mysql -u root -p -e "CREATE DATABASE apiasrlm;"
php artisan migrate
```

### 4.5 Running the Server

**Development (Octane with RoadRunner):**

```bash
php artisan octane:start --server=roadrunner --port=8000
```

**Reload after PHP file changes** — Octane caches workers in memory, so code changes require a reload:

```bash
php artisan octane:reload
```

**Standard Laravel dev server (no Octane):**

```bash
php artisan serve --port=8000
```

> **Important:** Always use `octane:reload` (not `octane:restart`) after editing PHP files when running under Octane.

### 4.6 API Reference

All endpoints are prefixed with `/api`. Protected routes require:

```
Authorization: Bearer <sanctum_token>
Accept: application/json
```

---

#### Auth — `/api/auth`

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/auth/login` | — | Login with email + password. Returns token, or `{ requires_setup: true }` for new employees |
| `POST` | `/auth/register` | — | Self-register as Block Cadre / Block Admin / District Admin |
| `POST` | `/auth/setup-password` | — | First-time password setup for admin-created employees (verified by `emp_id`) |
| `POST` | `/auth/forgot-password/verify` | — | Verify identity via email/mobile + Aadhaar; returns 64-char reset token (valid 60 min) |
| `POST` | `/auth/forgot-password/reset` | — | Reset password using token from verify step |
| `GET`  | `/auth/me` | ✓ | Get current user profile (name, email, mobile_number, aadhaar_number, date_of_birth, role, status, cadre_type) |
| `PUT`  | `/auth/profile` | ✓ | Update own mobile_number, aadhaar_number, date_of_birth |
| `POST` | `/auth/logout` | ✓ | Revoke current access token |

**Register request body:**

```json
{
  "role": "block_cadre | block | district",
  "name": "Full Name",
  "email": "user@example.com",
  "mobile_number": "9876543210",
  "aadhaar_number": "123456789012",
  "date_of_birth": "1990-01-15",
  "password": "secret123",
  "password_confirmation": "secret123",
  "district_id": 1,
  "block_id": 2,
  "cadre_type": "JS | KS | PS | BS | CRP-EP | BimaS | YS | BM",
  "designation_id": 3
}
```

---

#### User Management — `/api/users` _(auth required)_

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/users/pending` | List users pending approval |
| `GET`  | `/users/all` | List all users |
| `POST` | `/users/{id}/approve` | Approve a pending user |
| `POST` | `/users/{id}/reject` | Reject a pending user |

---

#### HR — `/api/hr` _(auth required)_

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/hr/designations` | List designations (cached 3 weeks) |
| `POST` | `/hr/designations` | Create designation |
| `PUT`  | `/hr/designations/{id}` | Update designation |
| `DELETE` | `/hr/designations/{id}` | Delete designation |
| `GET`  | `/hr/employees` | List employees (scoped by role/location) |
| `POST` | `/hr/employees` | Create employee + auto-create login account (password = null, set on first login) |
| `PUT`  | `/hr/employees/{id}` | Update employee (syncs name/email/mobile/aadhaar to linked users record) |
| `DELETE` | `/hr/employees/{id}` | Delete employee |
| `PATCH` | `/hr/employees/{id}/reset-password` | Force password reset (nulls password, deletes tokens) |
| `GET`  | `/hr/attendance` | Get attendance records |
| `POST` | `/hr/attendance` | Save attendance |
| `GET`  | `/hr/payroll` | Get payroll records |
| `POST` | `/hr/payroll` | Generate monthly salary |
| `PATCH` | `/hr/payroll/{id}/status` | Update payroll payment status |
| `DELETE` | `/hr/payroll/{id}` | Delete payroll record |

---

#### Financial — `/api/financial` _(auth required)_

**Banks**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/financial/banks` | List banks |
| `POST` | `/financial/banks` | Add bank |
| `PUT`  | `/financial/banks/{id}` | Update bank |
| `DELETE` | `/financial/banks/{id}` | Delete bank |
| `POST` | `/financial/banks/bulk` | Bulk upload banks from CSV |
| `POST` | `/financial/banks/pull-ifsc` | Fetch bank details by IFSC via razorpay/ifsc package |
| `GET`  | `/financial/banks/search` | Search banks by IFSC or name |

**Member Loans**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/financial/member-loans` | List loans (scoped by role/location) |
| `POST` | `/financial/member-loans` | Create loan |
| `GET`  | `/financial/member-loans/{id}` | Loan detail with computed NPA status + bank comparison |
| `PUT`  | `/financial/member-loans/{id}` | Update loan |
| `DELETE` | `/financial/member-loans/{id}` | Delete loan |
| `POST` | `/financial/member-loans/bulk` | Bulk import from CSV |
| `POST` | `/financial/member-loans/bank-reconcile` | Upload bank CSV; validates and flags discrepancies, sets bank_reconcile_date |
| `GET`  | `/financial/member-loans/export` | Export loans as CSV |
| `GET`  | `/financial/member-loans/{id}/payments` | List payments |
| `POST` | `/financial/member-loans/{id}/pay` | Record a payment |
| `DELETE` | `/financial/member-loans/{id}/payments/{pid}` | Delete a payment |

**SHG Loans** — same pattern as Member Loans under `/financial/shg-loans`

**Block Cadres**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/financial/cadres` | List cadres (scoped by block) |
| `POST` | `/financial/cadres` | Create cadre + linked login account |
| `PUT`  | `/financial/cadres/{id}` | Update cadre (syncs aadhaar/dob to linked users record) |
| `DELETE` | `/financial/cadres/{id}` | Delete cadre + linked user |

---

#### MIS — `/api/mis` _(auth required)_

Browse and manage the geographic hierarchy: Districts → Blocks → GPs → Villages → SHGs → Members.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/mis/districts` — `/mis/members` | Read hierarchy at each level |
| `PATCH` | `/mis/members/{memCode}` | Update member details |
| `PATCH` | `/mis/shgs/{shgCode}/toggle` | Activate/deactivate SHG |
| `PATCH` | `/mis/members/{memCode}/toggle` | Activate/deactivate member |

---

#### Mapping — `/api/mapping` _(auth required)_

Create new nodes in the hierarchy and remap existing ones to different parent nodes.

---

#### Public — `/api/public` _(no auth required)_

Geographic dropdown data for the registration form.

| Endpoint | Description |
|----------|-------------|
| `GET /public/geo/districts` | All districts |
| `GET /public/geo/blocks?district_id=` | Blocks in a district |
| `GET /public/designations?role=` | Designations filtered by role |

---

### 4.7 Models & Database Schema

#### Core Tables

| Table | Model | Purpose |
|-------|-------|---------|
| `users` | `User` | All login accounts; stores role, status, aadhaar_number, date_of_birth, mobile_number |
| `personal_access_tokens` | _(Sanctum)_ | API bearer tokens |
| `password_reset_tokens` | _(Laravel)_ | Forgot-password verification tokens (hashed; 60-min TTL) |
| `designations` | `Designation` | Job titles with associated role, salary defaults, EPF/PT rates |
| `employees` | `Employee` | Staff records; linked to `users` via `user_id`; stores aadhaar_number |
| `block_cadres` | `BlockCadre` | Field cadre workers; linked to `users`; stores aadhaar_number, date_of_birth, cadre_type |
| `employee_transfer_requests` | `EmployeeTransferRequest` | Cadre transfer requests between blocks |

#### Geographic Hierarchy

```
district → block → gp → village → shg → member
```

| Table | Key Column |
|-------|------------|
| `district` | `district_id` |
| `block` | `block_id` |
| `gp` | `gp_id` |
| `village` | `vill_id` |
| `shg` | `shg_code` |
| `member` | `mem_code` |

#### Financial Tables

| Table | Purpose |
|-------|---------|
| `bank` | Bank master (IFSC, branch, district, block) |
| `loan_schemes` | Loan scheme master (interest rate, tenure, scheme type) |
| `memberloan` | Individual member loans; tracks NPA status, bank_reconcile_date |
| `memberloan_payment` | Payment history per loan |
| `shgloan` | SHG group loans |
| `shgloan_payment` | SHG payment history |

#### HR Tables

| Table | Purpose |
|-------|---------|
| `emp_attendance` | Monthly attendance records |
| `emp_salary` | Monthly salary slips (gross, net, EPF, deductions) |

#### Computed Fields on Loan Detail

`MemberLoanController::formatRecord()` enriches each loan response with:

| Field | Description |
|-------|-------------|
| `computed_status` | `active` / `due` (1–29 days) / `overdue` (30–89 days) / `npa` (90+ days) / `closed` |
| `days_overdue` | Calendar days since `payment_date` |
| `computed_overdue_amount` | Estimated outstanding overdue balance |
| `bank_comparison` | Object comparing our records vs. bank CSV (`bank_loan_amount`, `bank_already_paid`, `bank_os_balance`), `has_discrepancy` flag, `reported_date` |
| `needs_kyc` | Boolean flag |

`bank_comparison` is always included in the response when `bank_reconcile_date` is set, even if no financial figures were uploaded (shown with an empty-state message in the UI).

---

### 4.8 Authentication

**Login flow:**
1. `POST /auth/login` → returns `access_token`
2. Frontend stores token in `localStorage`
3. All protected requests include `Authorization: Bearer <token>`

**First-time employee setup:**
- Admin-created employees have `password = null`
- Login returns `{ requires_setup: true, email, role }`
- Frontend redirects to `/setup-password`
- Employee sets password via `POST /auth/setup-password` (verified by `emp_id`)

**Forgot password flow (no email server needed):**
1. `POST /auth/forgot-password/verify` — provide email or mobile number + 12-digit Aadhaar
2. Server returns a 64-char reset token (stored hashed in `password_reset_tokens`, valid 60 minutes)
3. `POST /auth/forgot-password/reset` — provide token + new password

**Self-registration flow:**
1. User submits `/register` with role, personal details, Aadhaar (12 digits), DOB, and password
2. Account created with `status = pending`
3. Block/District/State Admin approves via `/users/management`
4. User can log in once approved

---

## 5. Frontend — React App

### 5.1 Prerequisites

- Node.js 18+
- npm

### 5.2 Installation

```bash
cd my-react-app
npm install
```

### 5.3 Environment Configuration

Create `.env` in the `my-react-app/` root:

```env
VITE_API_URL=http://localhost:8000/api
```

### 5.4 Running the Dev Server

```bash
npm run dev
# App available at http://localhost:5173
```

### 5.5 Building for Production

```bash
npm run build
# Output: dist/
```

### 5.6 Pages & Routes

| Path | Component | Auth Required | Description |
|------|-----------|---------------|-------------|
| `/` | `Welcome` | — | Landing page |
| `/login` | `Login` | — | Email + password login |
| `/register` | `Register` | — | Self-registration (role, Aadhaar, DOB, location) |
| `/forgot-password` | `ForgotPassword` | — | 2-step: verify identity → reset password |
| `/setup-password` | `SetupPassword` | — | First-time password setup for employees |
| `/profile` | `Profile` | Any role | View + edit own account (mobile, Aadhaar, DOB). Opens on Edit tab with `?tab=edit` |
| `/dashboard` | `Dashboard` | Any role | Role-aware home (block_cadre redirected to `/cadre`) |
| `/hr` | `HR` | state only | Employee management, designations |
| `/payroll` | `Payroll` | state/district/block | Monthly payroll generation |
| `/loans` | `Finance` | Any role | Member and SHG loan management, bank reconciliation (block_cadre redirected to `/cadre/loans`) |
| `/banks` | `Bank` | Any role | Bank master data, IFSC lookup, bulk upload |
| `/mis/*` | `MIS` | state/district/block | MIS hierarchy browser |
| `/mapping/*` | `Mapping` | state/district/block/cadre | Add/remap geographic entities |
| `/cadre` | `CadreHome` | block_cadre only | Mobile dashboard with loan stats |
| `/cadre/loans` | `CadreLoans` | block_cadre only | Mobile loan creation and listing |
| `/cadre/account` | `CadreAccount` | block_cadre only | Mobile account settings (Aadhaar, DOB, mobile) |
| `/users/cadre` | `CadrePage` | Any role | Manage block cadre workers |
| `/users/employees` | `EmployeesPage` | district/block | Manage employees |
| `/users/management` | `UserManagement` | state/district/block | Approve/reject pending registrations |
| `/users/transfers` | `TransferRequests` | state/district/block | Cadre transfer requests |

#### API Client Modules (`src/api/`)

| Module | Covers |
|--------|--------|
| `auth.js` | Login, register, profile fetch/update, forgot-password verify/reset |
| `financial.js` | Loans, payments, banks, cadres |
| `hr.js` | Employees, designations, attendance, payroll |
| `mis.js` | MIS hierarchy reads |
| `mapping.js` | Geographic hierarchy writes |
| `userManagement.js` | User approval and transfer requests |
| `client.js` | Base fetch wrapper (injects Bearer header, throws on non-2xx) |
| `apiCache.js` | localStorage response cache (10 min for mutable data, 3 weeks for reference data) |

---

## 6. User Roles

### State Admin (`state`)
- Full read/write access across all districts
- Manages all employees and designations
- Generates payroll for all staff
- Accesses all financial data, loan schemes, bank reconciliation
- Approves/rejects new user registrations
- Reviews cadre transfer requests

### District Admin (`district`)
- Scoped to their assigned district
- Manages employees within the district
- Views and manages loans across all blocks in the district
- Approves new user registrations within their district

### Block Admin (`block` / `block_admin`)
- Scoped to their assigned block
- Creates and manages block cadre workers
- Creates and tracks member and SHG loans
- Manages block-level employees
- Accesses **My Profile** and **Account Settings** from the top-right navbar dropdown

### Block Cadre (`block_cadre`)
- Mobile-first interface only (`/cadre/*`)
- Views and creates member and SHG loans in their block
- Bottom navigation: **Home** | **Loans** | **Account** | **Logout**
- **Account** (`/cadre/account`) — view/edit mobile number, Aadhaar number, date of birth

---

## 7. Key Features

### Financial Inclusion

**Loan Lifecycle**
- Full lifecycle: create → EMI tracking → NPA detection → closure
- NPA status computed from `payment_date`:
  - `active` — current
  - `due` — 1–29 days overdue
  - `overdue` — 30–89 days
  - `npa` — 90+ days
- EMI calculated using flat/simple interest: `(P + P × rate × tenure/12) / months`
- Bulk import via CSV; export to CSV

**Bank Reconciliation**
- Upload a bank-provided CSV to compare bank figures against portal records
- Validates Loan Number, Mobile Number, Bank Account Number — any 2 of 3 must match
- Decimal loan IDs (Excel formatting artifact) are rejected with a clear error message
- Discrepancies (amount mismatches) are highlighted per row
- `bank_reconcile_date` stamped on every reconciled loan
- Loan detail shows "Bank vs Our Records" section with match/discrepancy badge and upload timestamp — visible whenever `bank_reconcile_date` is set, even if no figures were uploaded

### HR Module

- Employee record management with salary components (basic, mobile allowance, professional development)
- EPF and professional tax deductions; auto-computed gross/net
- Monthly attendance recording and payroll generation
- Admin-created employees get a login account with `password = null`; employee sets it on first login
- Employee updates sync `phone → users.mobile_number` and `aadhaar_number → users.aadhaar_number`

### MIS & Mapping

- Browse the full District → Block → GP → Village → SHG → Member hierarchy
- Toggle SHG and member active/inactive status
- Create new entities at any level; remap to different parents
- Public geo endpoints for registration dropdowns (no auth required)

### Account & Identity

- All users can update Aadhaar (12 digits), date of birth, and mobile number through their profile
- Aadhaar is also used for forgot-password identity verification
- **Block Cadre:** `/cadre/account` (mobile-optimized)
- **All other roles:** `/profile` — accessible from navbar dropdown ("My Profile" or "Account Settings")
  - "Account Settings" navigates directly to the Edit tab via `?tab=edit` query param

### Block Cadre Management

- Block Admin creates cadre workers linked to SHG member records
- Cadre types: JS (Jeevika Sakhi), KS (Krishi Sakhi), PS (Pashu Sakhi), BS (Bank Sakhi), CRP-EP, BimaS (Bima Sakhi), YS (Yuva Sakhi), BM (Bank Mitra)
- Each cadre gets a linked login account on creation; stays `pending` until approved
- Aadhaar and DOB stored on cadre record and synced to linked user on update

---

## 8. Project Architecture

### Backend

```
Request → routes/api.php → Controller → Model → MySQL
```

**Key decisions:**
- **Octane + RoadRunner** — PHP workers are kept in memory between requests for high throughput; workers must be reloaded (`octane:reload`) after code changes
- **Sanctum tokens** — Stateless API auth; no cookies; token stored in frontend `localStorage`
- **Role scoping in controllers** — Controllers automatically filter queries by `district_id`/`block_id` based on `$request->user()->role`
- **User sync pattern** — Employee and BlockCadre updates propagate fields (name, email, mobile_number, aadhaar_number, date_of_birth) to the linked `users` record to keep login credentials in sync
- **No Redis required** — Queue, cache, and session all use the MySQL `database` driver

### Frontend

```
App.jsx (Router)
└── AuthProvider (AuthContext — token + user in memory + localStorage)
      ├── Public routes (Login, Register, ForgotPassword, SetupPassword)
      └── ProtectedRoute (checks token + allowedRoles)
            ├── AppLayout (state / district / block roles)
            │     ├── AppNavbar  — top bar with notification bell + profile dropdown
            │     ├── AppSidebar — icon sidebar with navigation links
            │     └── <Page />
            └── CadreLayout (block_cadre only — mobile-first)
                  ├── Sticky top header
                  ├── <Page />
                  └── Fixed bottom navigation (Home | Loans | Account | Logout)
```

**Key decisions:**
- **No component library** — Pure Tailwind CSS; consistent `rounded-xl`, `border-gray-200`, `shadow-sm` design language
- **API response caching** — `apiCache.js` caches GET responses in `localStorage` with per-endpoint TTLs; mutations bust affected cache keys
- **Dual UI for cadre** — Block cadre workers get a completely separate, mobile-first layout; desktop users are automatically redirected away from `/cadre/*` routes
- **Modals over pages** — Loan creation, employee editing, and bank reconciliation use slide-in modals to preserve page context
- **`CadreRedirect` component** — `block_cadre` users hitting `/dashboard` or `/loans` are immediately redirected to `/cadre` and `/cadre/loans` respectively

---

## Quick Start (Development)

```bash
# 1. Start MySQL and create the database
mysql -u root -p -e "CREATE DATABASE apiasrlm;"

# 2. Backend
cd apiasrlm
composer install
cp .env.example .env
# Edit .env — set DB credentials, then:
php artisan key:generate
php artisan migrate
php artisan octane:start --server=roadrunner --port=8000

# 3. Frontend (separate terminal)
cd my-react-app
npm install
echo "VITE_API_URL=http://localhost:8000/api" > .env
npm run dev

# App available at http://localhost:5173
```

After any PHP file change, reload the Octane workers:

```bash
php artisan octane:reload
```

---

*Built for Assam State Rural Livelihoods Mission — ASRLM Portal*
