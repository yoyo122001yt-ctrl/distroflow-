# DistroFlow - Wholesale Distribution Warehouse Management System

A complete B2B wholesale distribution system for warehouses that buy products from manufacturers and deliver to retail stores (grocery, convenience, pharmacy, restaurant, hotel).

## System Requirements

- PHP 8.2+
- MySQL 8.0+
- Node.js 18+
- Composer 2+
- HTTPS required for PWA (use ngrok for testing)

## Architecture

```
distroflow-system/
├── laravel-backend/    # REST API (Laravel 11)
├── react-frontend/     # Web Dashboard (React 18 + Tailwind)
├── driver-pwa/         # Driver Mobile App (PWA + Offline)
└── docker-compose.yml  # Docker setup
```

## Features

### Warehouse Operations
- Receive products from suppliers with batch/expiry tracking
- Storage location management (racks, shelves, zones)
- FEFO picking (First Expired First Out)
- Truck loading by route
- Cycle counting and stock adjustments
- Return processing

### Retail Store (Customer) Management
- Store profiles with type classification
- Credit limits and payment terms (net_15, net_30, COD)
- Store-specific pricing
- Balance tracking and credit aging

### Order Management
- Multi-channel order entry (phone, app, driver visit)
- Credit limit checking with manager approval workflow
- Order picking, loading, delivery tracking

### Route & Driver Management
- Geographic route definition with optimized stop ordering
- Driver and truck assignment
- Daily route manifest generation
- Driver mobile app with full offline support

### Driver Mobile App (PWA)
- View today's route with stops in order
- Record actual delivered quantities
- Capture product returns (expired, damaged)
- Collect cash/check payments
- Capture customer signatures
- Take shelf photos (merchandising verification)
- Record shelf stock rotation
- View current truck inventory
- **Fully offline-capable** with automatic sync

### Inventory Management
- Warehouse stock with batch/expiry tracking
- Truck stock tracking (mobile warehouse)
- Real-time stock visibility
- FEFO picking algorithm
- Low stock and expiry alerts

### Driver Settlement
- Start shift: record starting inventory
- End shift: cash and inventory reconciliation
- Formula: `Expected_Cash = Total_Sales - Total_Returns`
- Variance threshold alerts for manager review

### Financials
- Invoice generation per delivery
- Payment tracking (cash, check, bank transfer)
- Store credit management
- Profit per route, driver, store
- Accounts receivable aging

### Reports
- Daily route settlement report
- Sales by store, product, route
- Warehouse stock valuation (FIFO/AVG)
- Expiry report (30/60/90 days)
- Driver performance metrics
- Credit aging report
- Profit & loss by route
- Truck inventory report

## Quick Install

### Option 1: Manual Setup

```bash
# 1. Backend Setup
cd laravel-backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# 2. Web Frontend Setup
cd ../react-frontend
npm install
npm run build

# 3. Driver PWA Setup
cd ../driver-pwa
npm install
npm run build

# 4. Start Servers
php artisan serve  # Backend: http://localhost:8000
npm run dev --prefix ../react-frontend  # Web: http://localhost:5173
npm run dev --prefix ../driver-pwa  # Driver: http://localhost:5174
```

### Option 2: Docker Setup

```bash
docker-compose up -d
```

## Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@distroflow.com | password |
| Warehouse Manager | manager@distroflow.com | password |
| Driver 1 | driver1@distroflow.com | password |
| Driver 2 | driver2@distroflow.com | password |
| Sales Rep | sales@distroflow.com | password |
| Accountant | accountant@distroflow.com | password |

## Cron Jobs

```bash
# Check and update expiry statuses daily
* * * * * cd /path/to/laravel-backend && php artisan schedule:run >> /dev/null 2>&1

# Or run manually:
php artisan distroflow:check-expiry     # Check expiring/expired batches
php artisan distroflow:generate-routes  # Generate daily route assignments
php artisan distroflow:send-invoices    # Send pending invoices
php artisan distroflow:check-credit     # Check store credit limits
```

## API Documentation

### Authentication
```
POST /api/auth/login     # Login (returns Bearer token)
POST /api/auth/register  # Register new user
POST /api/auth/logout    # Logout (revokes token)
GET  /api/auth/me        # Current user profile
```

### Products
```
GET    /api/products          # List products (paginated)
POST   /api/products          # Create product
GET    /api/products/{id}     # Get product
PUT    /api/products/{id}     # Update product
DELETE /api/products/{id}     # Delete product
GET    /api/products/{id}/batches  # Product batch history
```

### Warehouse Operations
```
POST /api/warehouse/receive   # Receive goods from supplier
GET  /api/warehouse/inventory # Current warehouse stock
POST /api/warehouse/pick      # Generate pick list (FEFO)
POST /api/warehouse/load      # Load truck for route
POST /api/warehouse/adjust    # Stock adjustment
GET  /api/warehouse/expiring  # Products expiring soon
```

### Retail Stores
```
GET    /api/stores              # List stores
POST   /api/stores              # Create store
GET    /api/stores/{id}         # Store details
PUT    /api/stores/{id}         # Update store
DELETE /api/stores/{id}         # Delete store
GET    /api/stores/{id}/orders  # Store order history
GET    /api/stores/{id}/balance # Credit balance
POST   /api/stores/{id}/credit-limit  # Update credit limit
POST   /api/stores/{id}/prices  # Set store-specific pricing
```

### Sales Orders
```
GET    /api/orders              # List orders
POST   /api/orders              # Create order
GET    /api/orders/{id}         # Order details
PUT    /api/orders/{id}         # Update order
POST   /api/orders/{id}/approve       # Approve (check credit)
POST   /api/orders/{id}/assign-route  # Assign to route
POST   /api/orders/{id}/cancel        # Cancel order
```

### Routes
```
GET    /api/routes              # List routes
POST   /api/routes              # Create route
GET    /api/routes/{id}         # Route details
PUT    /api/routes/{id}         # Update route
DELETE /api/routes/{id}         # Delete route
GET    /api/routes/{id}/stops   # Get stops in order
POST   /api/routes/{id}/optimize    # Optimize stop order (Nearest Neighbor)
POST   /api/routes/{id}/assign      # Assign driver & truck
GET    /api/routes/{id}/manifest    # Generate manifest
```

### Driver Mobile
```
GET    /api/driver/today-route              # Today's route
GET    /api/driver/route-stops              # Stops in sequence
GET    /api/driver/stop/{stopId}            # Stop details
POST   /api/driver/stop/{stopId}/arrive     # Arrive at store
POST   /api/driver/stop/{stopId}/deliver    # Record delivery
POST   /api/driver/stop/{stopId}/collect-payment  # Record payment
POST   /api/driver/stop/{stopId}/signature  # Capture signature
POST   /api/driver/stop/{stopId}/photos     # Upload photos
GET    /api/driver/truck-inventory          # Current truck stock
POST   /api/driver/truck-inventory/sync     # Sync offline data
POST   /api/driver/start-shift              # Start shift
POST   /api/driver/end-shift                # End shift (settlement)
```

### Settlements
```
GET  /api/settlements              # List settlements
GET  /api/settlements/{id}         # Settlement details
POST /api/settlements/{id}/approve # Approve settlement
GET  /api/settlements/unreconciled # Pending reconciliations
```

### Reports
```
GET /api/reports/route-settlement  # Daily route settlement
GET /api/reports/sales-by-store    # Sales by store
GET /api/reports/sales-by-product  # Sales by product
GET /api/reports/expiry            # Expiry report
GET /api/reports/driver-performance # Driver metrics
GET /api/reports/credit-aging      # Credit aging
GET /api/reports/profit-by-route   # Profit per route
GET /api/reports/truck-inventory   # Truck stock report
POST /api/reports/export           # Export any report
```

### Invoices
```
GET  /api/invoices              # List invoices
GET  /api/invoices/{id}         # Invoice details
POST /api/invoices/{id}/send    # Email invoice
GET  /api/invoices/{id}/pdf     # Download PDF
POST /api/invoices/{id}/record-payment  # Record payment
```

## Driver App Setup (PWA)

1. Visit `http://localhost:5174` on the driver's mobile device
2. Log in with driver credentials
3. Click "Install App" or use browser's "Add to Home Screen"
4. The app works offline automatically
5. Data syncs when connection is restored

## Key Business Logic

### FEFO Picking
The system always picks batches with the earliest expiry date first. This is critical for food/beverage distribution to prevent expired products reaching retail stores.

### Driver Settlement
```
Expected_Cash = Total_Sales_Value - Total_Returns_Value
Cash_Variance = Expected_Cash - Actual_Cash_Collected

Expected_End_Inventory = Start_Inventory + Loaded - Sales - Returns
Inventory_Variance = Expected_End_Inventory - Actual_End_Inventory
```

If variance exceeds configured threshold, the settlement is flagged for manager review.

### Credit Check
Before order approval: `Current_Balance + Order_Amount <= Credit_Limit`

If exceeded, order requires manager approval.

### Route Optimization
Uses Nearest Neighbor algorithm with Haversine distance calculation to optimize delivery stop ordering.

## Tech Stack

- **Backend:** Laravel 11 + MySQL + Sanctum
- **Web Frontend:** React 18 + Tailwind CSS + Recharts
- **Mobile:** PWA with offline support (IndexedDB + Service Workers)
- **Maps:** Leaflet.js
- **Reports:** Laravel Excel / Dompdf
- **Authentication:** Laravel Sanctum (token-based)

## User Roles

| Role | Access |
|------|--------|
| Admin | Full system access |
| Warehouse Manager | Inventory, receiving, picking, loading |
| Sales Rep | Create orders, manage stores, reports |
| Driver | Mobile app only (route, deliveries, settlement) |
| Accountant | Invoices, payments, settlements, financial reports |
| Viewer | Read-only access |

## License

Proprietary - All Rights Reserved
