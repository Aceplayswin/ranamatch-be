# Guide: India Lotto API Integration Documentation

This document details where the India Lotto files were added and how the API integration functions within the Velplay365 backend.

---

## 1. API Callback Endpoints (The Core Logic)

The provider (Running10 / India Lotto) sends requests to your server to check user balances, deduct bets, and add winnings. These callbacks are handled by two specific files:

### A. Balance Checking Endpoint
* **File:** `route-paths/callbacks/india-lotto-get-point.php`
* **Purpose:** Responds to the provider's `get_point` request. It validates the user session using `INDIALOTTO_SECRET_KEY` and returns the user's available balance converted to the provider's format (1 INR = 1000 units).

### B. Transaction & Settlement Endpoint
* **File:** `route-paths/callbacks/india-lotto-change-point.php`
* **Purpose:** Handles all `change_point` requests:
  * **Type 3 (Bet Placing):** Deducts balance and inserts a `wait` status record into `tblmatchplayed`.
  * **Type 4 (Payout) / Type 5 (Refund) / Type 9 (Jackpot):** Adds balance and updates the `tblmatchplayed` record to `settled`, assigning a `win` or `loss` result.
  * **Logging:** Inserts admin transaction history into `tblotherstransactions`.
  * **Important:** It processes the 1000:1 currency conversion logic securely.

---

## 2. API Routing Configuration

To ensure the provider's requests reach the files above, the backend router is configured to map their URLs.

* **File:** `router/route-paths.php`
* **Configuration Added:**
  ```php
  } elseif ($routePath == 'india-lotto-callback/get_point') {
      require_once dirname(__DIR__) . '/route-paths/callbacks/india-lotto-get-point.php';
      exit;
  } elseif ($routePath == 'india-lotto-callback/change_point') {
      require_once dirname(__DIR__) . '/route-paths/callbacks/india-lotto-change-point.php';
      exit;
  }
  ```

---

## 3. Security Configuration

The integration relies on authentication credentials provided by India Lotto.

* **File:** `security/constants.php` or `security/constant2.php`
* **Configuration Added:**
  * `INDIALOTTO_SECRET_KEY` - Used to generate and validate MD5 signatures.
  * `INDIALOTTO_PLATFORM_CODE` - Used to verify the requesting platform.

---

## 4. Database Schema & Notification System

During the integration, two critical database behaviors were handled:

1. **MySQL Strict Mode Compliance:** 
   The `tblmatchplayed` table requires all 18 columns to have a value on insertion (it does not accept NULL for many fields). The integration explicitly inserts default values (e.g., `tbl_lot_size = '1'`, `tbl_odds = '1'`) to prevent query crashes.

2. **Fixing Premature Loss Notifications:**
   Velplay365's frontend notification system (`route-paths/load-game-notifications.php`) automatically triggers popups for any game not marked as `wait`. 
   * **Fix Implemented:** When a bet (Type 3) is placed, the record is inserted into `tblmatchplayed` with `tbl_match_status = 'wait'`.
   * **Result:** The system waits silently. Only when India Lotto sends a Type 4 Payout does the status change to `settled`, which properly triggers the Win/Loss notification popup.

---

## 5. Debugging & Logs

If India Lotto transactions fail or show inconsistencies, check the dedicated callback logs.

* **Log File:** `route-paths/callbacks/callback_logs.txt`
* **What it shows:** Incoming payloads, generated signatures, MD5 mismatches, and execution status of the balance queries.
