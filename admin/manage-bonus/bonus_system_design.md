    # Bonus System Architecture Plan

    This document outlines the database structure and functional logic for the unified Bonus Management system.

    ## 1. Database Schema Design (SQL)

    To support the 5-step promotion builder and the redemption tracking, we require the following relational structure:

    ### A. Core Bonus Table (`tbl_bonuses`)
    Stores main identity and general settings from Step 1 and 2.
    ```sql
    CREATE TABLE tbl_bonuses (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(50), -- mass, single_account, cashback, etc.
        coupon_code VARCHAR(100) UNIQUE,
        priority INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        
        -- Redemption Logic
        redemption_type VARCHAR(50), -- % of deposit, fixed, etc.
        amount DECIMAL(10, 2),
        bonus_category VARCHAR(50), -- Casino, Sports, Both
        min_deposit DECIMAL(10, 2),
        max_redeem_value DECIMAL(10, 2),
        
        -- Deposit Restrictions
        is_first_deposit BOOLEAN DEFAULT FALSE,
        is_second_deposit BOOLEAN DEFAULT FALSE,
        is_third_deposit BOOLEAN DEFAULT FALSE,
        is_new_player_only BOOLEAN DEFAULT FALSE,
        is_auto_redeem BOOLEAN DEFAULT FALSE,
        
        -- Platform Restrictions
        allow_download BOOLEAN DEFAULT TRUE,
        allow_instant BOOLEAN DEFAULT TRUE,
        allow_mobile BOOLEAN DEFAULT TRUE,
        
        -- Time Scheduling
        start_at DATETIME,
        end_at DATETIME,
        
        -- Usage Limits
        limit_per_player INT,
        limit_daily INT,
        limit_weekly INT,
        limit_monthly INT,
        limit_total INT,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ```

    ### B. Provider Specific Rules (`tbl_bonus_providers`)
    Handles per-provider eligibility (Step 2) and wagering multipliers (Step 5).
    ```sql
    CREATE TABLE tbl_bonus_providers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        bonus_id INT,
        provider_name VARCHAR(100), -- SA, Jili, etc.
        eligible_percent INT DEFAULT 100, -- From Step 2
        wagering_multiplier DECIMAL(5, 2) DEFAULT 1.00, -- From Step 5
        is_wagering_enabled BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (bonus_id) REFERENCES tbl_bonuses(id) ON DELETE CASCADE
    );
    ```

    ### C. Multilingual Content (`tbl_bonus_content`)
    Stores the text and images from Step 3 for different languages.
    ```sql
    CREATE TABLE tbl_bonus_content (
        id INT PRIMARY KEY AUTO_INCREMENT,
        bonus_id INT,
        lang_code VARCHAR(10), -- en, es, hi
        title VARCHAR(255),
        description TEXT,
        image_path VARCHAR(255),
        terms_conditions TEXT,
        FOREIGN KEY (bonus_id) REFERENCES tbl_bonuses(id) ON DELETE CASCADE
    );
    ```

    ### D. Security Filters (`tbl_bonus_abuse`)
    Stores the security thresholds from Step 4.
    ```sql
    CREATE TABLE tbl_bonus_abuse (
        bonus_id INT PRIMARY KEY,
        similarity_threshold_percent INT DEFAULT 30,
        exclude_days_deposited_played INT,
        exclude_days_played INT,
        FOREIGN KEY (bonus_id) REFERENCES tbl_bonuses(id) ON DELETE CASCADE
    );
    ```

    ---

    ## 2. Table Relationships & Connections

    The bonus system doesn't live in isolation; it connects to your existing user and financial tables to make decisions.

    ### Relationship Map
    ```mermaid
    erDiagram
        USERS ||--o{ BONUS_REDEMPTIONS : "redeems"
        RECHARGES ||--|| BONUS_REDEMPTIONS : "triggers"
        BONUSES ||--o{ BONUS_REDEMPTIONS : "defines rules"
        BONUSES ||--o{ BONUS_PROVIDERS : "sets wagering"
        BONUSES ||--o{ BONUS_CONTENT : "has translations"
        BONUSES ||--o{ BONUS_ABUSE : "has security"

        USERS {
            int id
            decimal balance
            int deposit_count
        }
        RECHARGES {
            int id
            int user_id
            decimal amount
            string status
        }
        BONUS_REDEMPTIONS {
            int id
            int bonus_id
            int user_id
            decimal bonus_amount
            decimal wagering_required
            decimal wagering_completed
            string status "active/completed/expired"
        }
    ```

    ### How they Connect:

    1.  **Connection to `tblusers`**:
        - The system checks the user's `deposit_count` to decide if they qualify for the "1st, 2nd, or 3rd Deposit" bonus options from Step 2.
        - When a bonus is completed, the amount is added to the user's main `balance`.

    2.  **Connection to `tblrecharge` (Deposit Table)**:
        - Every successfully processed deposit is a **Trigger**. 
        - The system looks for an active bonus in `tbl_bonuses` where `is_first_deposit` (or 2nd/3rd) matches the user's current deposit sequence.

    3.  **Connection to Gaming/Transaction Logs**:
        - As a player rotates games, the system monitors their betting volume.
        - It checks `tbl_bonus_providers` to see the **Multiplier**. 
        - *Example*: If the multiplier is **10x** and they received **$100**, the system tracks their bets until they reach **$1000** total volume.

    ---

    ## 4. Understanding the Wagering Multiplier

    The **Wagering Multiplier** (often called "Rollover" or "Turnover") is the rule that determines when a bonus is officially "Cleared" and ready for withdrawal.

    ### The Basic Formula
    $$Wagering\ Requirement = Bonus\ Amount \times Multiplier$$

    **Example:**
    - You give a player a **$100** bonus.
    - You set the multiplier to **20x**.
    - The player must place a total of **$2,000** in bets before they can withdraw that $100.

    ### Provider-Specific Weighting (Step 5 Logic)
    In our new design, you can set **different multipliers for different providers**. This is used to balance risk:

    - **High-Risk Games (Slots)**: You might set a low multiplier (e.g., **10x**) for providers like **Jili** or **CQ9** to encourage play.
    - **Low-Risk Games (Live Casino)**: You might set a high multiplier (e.g., **50x**) for providers like **Evolution** because the player has a better chance of winning (e.g., on Blackjack or Baccarat).

    ### How it tracks in the Background:
    1.  **Bet Placement**: Every time the player clicks "Spin" or "Place Bet" on a provider (e.g., SA Gaming).
    2.  **Lookup**: The system looks at `tbl_bonus_providers` for the `wagering_multiplier` tied to "SA".
    3.  **Calculation**: It calculates the contribution.
    4.  **Threshold**: Once the `wagering_completed` column in the redemption record reaches the `wagering_required` target, the system sends an "Unlock" signal.
    5.  **Payout**: The Bonus funds move to the Main Balance.

    This system gives you total control over your profit margins by ensuring players "earn" their bonuses through active play.
