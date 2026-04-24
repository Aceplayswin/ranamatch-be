/* Theme Variables System */
:root {
    /* Base Fonts */
    --font-body: 'DM Sans', sans-serif;
    --font-display: 'Archivo Black', sans-serif;

    /* Theme-specific Colors (Default Dark) */
    --page-bg: #0d1117;
    --panel-bg: #161b22;
    --border-dim: rgba(255, 255, 255, 0.07);
    --text-main: #f1f5f9;
    --text-dim: #94a3b8;
    --side-bg: #161b22;
    --side-text: #94a3b8;
    --side-text-active: #ffffff;
    --side-accent: #3b82f6;
    --side-border: rgba(255, 255, 255, 0.06);
    --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    --input-bg: rgba(255, 255, 255, 0.07);
    --input-border: rgba(255, 255, 255, 0.12);
    --table-header-bg: rgba(255, 255, 255, 0.03);
    --table-row-hover: rgba(255, 255, 255, 0.05);

    /* Accent Colors */
    --accent-blue: #3b82f6;
    --accent-emerald: #10b981;
    --accent-rose: #f43f5e;
    --accent-amber: #f59e0b;
    --accent-cyan: #06b6d4;

    /* Dashboard Specific Card Colors (Premium Navy/Teal) */
    --c1:  #0d4f3c; --c3:  #0a4a5e; --c2:  #1e3a5f; --c5:  #4a1942;
    --c4:  #1c3d5a; --c6:  #3d2244; --c7:  #162447; --c8:  #11213e;
    --c9:  #1e2530; --c10: #0b3d2e; --c11: #14394a; --c12: #2d2010;

    --s1: #10b981; --s2: #3b82f6; --s3: #06b6d4; --s4: #60a5fa;
    --s5: #a78bfa; --s6: #c084fc; --s7: #38bdf8; --s8: #7dd3fc;
    --s9: #94a3b8; --s10: #34d399; --s11: #22d3ee; --s12: #d4a847;

    /* Status Colors */
    --status-success: #10b981;
    --status-warning: #f59e0b;
    --status-danger: #ef4444;
    --status-info: #3b82f6;

    /* UI Tokens (Typography) */
    --fs-xs: 11px;
    --fs-sm: 12px;
    --fs-base: 14px;
    --fs-md: 16px;
    --fs-lg: 18px;
    --fs-xl: 24px;
    --fs-xxl: 32px;

    /* UI Tokens (Spacing) */
    --sp-xs: 4px;
    --sp-sm: 8px;
    --sp-base: 16px;
    --sp-md: 24px;
    --sp-lg: 32px;
    --sp-xl: 48px;

    /* UI Tokens (Radius) */
    --radius-sm: 6px;
    --radius-base: 12px;
    --radius-md: 16px;
    --radius-lg: 24px;
}

/* Light Theme Overrides */
[data-theme="light"] {
    --page-bg: #f8fafc;
    --panel-bg: #ffffff;
    --border-dim: rgba(0, 0, 0, 0.1);
    --text-main: #0f172a;
    --text-dim: #475569;
    --side-bg: #ffffff;
    --side-text: #475569;
    --side-text-active: #0f172a;
    --side-accent: #2563eb;
    --side-border: rgba(0, 0, 0, 0.08);
    --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    --input-bg: #f1f5f9;
    --input-border: #cbd5e1;
    --table-header-bg: #f8fafc;
    --table-row-hover: #f1f5f9;

    /* Accent Colors for Light */
    --accent-blue: #2563eb;
    --accent-emerald: #059669;
    --accent-rose: #e11d48;
    --accent-amber: #d97706;
    --accent-cyan: #0891b2;

    /* Dashboard Cards in Light Mode (Softer versions) */
    --c1: #e6fffa; --c3: #e0f2fe; --c2: #f0f9ff; --c5: #fdf2f8;
    --c4: #f8fafc; --c6: #faf5ff; --c7: #eff6ff; --c8: #f1f5f9;
    --c9: #f8fafc; --c10: #f0fdf4; --c11: #ecfeff; --c12: #fffbeb;
    
    --s1: #059669; --s2: #2563eb; --s3: #0891b2; --s4: #3b82f6;
    --s5: #7c3aed; --s6: #9333ea; --s7: #0284c7; --s8: #0ea5e9;
    --s9: #475569; --s10: #10b981; --s11: #06b6d4; --s12: #b45309;

    /* Status Colors (Deeper for Light Mode) */
    --status-success: #059669;
    --status-warning: #d97706;
    --status-danger: #dc2626;
    --status-info: #2563eb;
}
