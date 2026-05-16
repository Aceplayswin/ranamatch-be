# Guide: Adding a New Game Provider (e.g., Huidu) & Games

This document outlines the standard procedure for integrating a new game provider (like Huidu) and their games into the Velplay365 platform. 

The platform uses a split architecture: React/Vite for the frontend, and raw PHP for the backend API and routing.

---

## 1. Backend Configuration & API Integration

### A. Define Provider Credentials
Add the new provider's API endpoint URLs, Secret Keys, and Platform Codes to your configuration files.
* **File:** `security/constants.php` or `security/constant2.php`
* **Action:** Define constants like `HUIDU_API_URL`, `HUIDU_SECRET_KEY`, etc.

### B. Implement Game Launch Logic
When a user clicks a game, the frontend sends a request to launch the game URL.
* **File:** `route-paths/request-play-games.php`
* **Action:** 
  1. Add a conditional block for the new provider (e.g., `if ($game_provider == 'Huidu')`).
  2. Call the provider's API to generate a session token or game launch URL.
  3. Return the `game_url` in the standard JSON response format.

### C. Create Provider Callback Endpoints
The provider will ping your server to check balances, deduct bets, and add winnings.
* **Directory:** `route-paths/callbacks/`
* **Action:** Create dedicated callback files (e.g., `huidu-callback.php` or separate `get_point` / `change_point` files).
* **Important Database Rules:**
  * **Balance Updates:** Update `tblusersdata.tbl_balance`.
  * **History:** Insert every transaction into `tblotherstransactions` (for admin view).
  * **Game Records:** Insert bet records into `tblmatchplayed`. *CRITICAL: Set `tbl_match_status` to `'wait'` for new bets, and `'settled'` when payouts arrive to prevent premature notification popups.*
  * **Strict Mode:** Provide default values for all required columns in `tblmatchplayed` (e.g., `tbl_lot_size = '1'`, `tbl_odds = '1'`) to prevent MySQL crashes.

### D. Register the Callbacks in Router
* **File:** `router/route-paths.php`
* **Action:** Add the endpoint routes (e.g., `elseif ($routePath == 'huidu-callback/seamless')`) and require the corresponding file from the `callbacks` directory.

---

## 2. Frontend Configuration & UI

### A. Add Game Data
Store the list of games and their icons to display on the frontend.
* **Directory:** `velplay_frontend/src/components/jsondata/`
* **Action:** Create a new file (e.g., `huidu.js`) exporting an array of game objects:
  ```javascript
  export const huiduGames = [
      {
          "Game Name": "Dragon Treasure",
          "Game UID": "huidu_dragon_123",
          "icon": "https://example.com/icons/dragon.png"
      }
  ];
  ```

### B. Display Games in UI
* **Files:** `velplay_frontend/src/components/home/ProviderSelection.jsx` or `Navbar.jsx`
* **Action:** Import the game list and map through it to render game cards. Ensure the `onClick` handler passes the `Game Name` and `Game UID` to your API launch function.

---

## 3. Admin Panel Updates (Optional)

If the new provider needs to be toggleable or requires custom reporting in the Admin dashboard:
* **Files:** `admin/manage-games/index.php`
* **Action:** Ensure the new provider name maps correctly in your SQL queries so admins can filter the game history by this new provider.
