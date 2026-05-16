import React from 'react';
import { Link } from 'react-router-dom';

const ForgotPassword = () => {
  return (
    <div className="bg-premium">
      <div className="auth-overlay">
        <header className="premium-header">
          <div className="brand" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <img 
              src="/logo.png" 
              alt="" 
              style={{ height: '24px', width: 'auto', display: 'block' }} 
              onError={(e) => e.target.style.display = 'none'} 
            />
            <h1 className="brand-font gold-text" style={{ fontSize: '22px', margin: 0, letterSpacing: '2px' }}>AURA</h1>
            <div className="header-separator"></div>
            <p style={{ fontSize: '10px', color: '#888', letterSpacing: '1px', margin: 0 }}>PREMIUM HUB</p>
          </div>
          <div className="nav-dummy" style={{ display: 'flex', gap: '20px' }}>
            <span style={{ color: '#aaa', fontSize: '11px', cursor: 'pointer', fontWeight: '600' }}>HOME</span>
            <span style={{ color: '#aaa', fontSize: '11px', cursor: 'pointer', fontWeight: '600' }}>PROMOTIONS</span>
            <span style={{ color: '#aaa', fontSize: '11px', cursor: 'pointer', fontWeight: '600' }}>SUPPORT</span>
            <span style={{ color: '#aaa', fontSize: '11px', cursor: 'pointer', fontWeight: '600' }}>CONTACT</span>
          </div>
        </header>

        <div className="auth-container">
          <div className="glass-card gold-glow">
            <div style={{ textAlign: 'center', marginBottom: '30px' }}>
              <h2 className="brand-font card-title">Recovery</h2>
              <div style={{ width: '30px', height: '2px', background: '#d4af37', margin: '0 auto 15px' }}></div>
              <p style={{ color: '#888', fontSize: '13px' }}>Enter your email for instructions</p>
            </div>
            
            <form onSubmit={(e) => e.preventDefault()}>
              <div style={{ marginBottom: '25px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>EMAIL ADDRESS</label>
                <input 
                  type="email" 
                  className="input-premium" 
                  style={{ padding: '10px', fontSize: '14px' }}
                  placeholder="Enter your email"
                  required
                />
              </div>
              
              <button type="submit" className="btn-primary" style={{ width: '100%', padding: '12px', borderRadius: '6px', fontSize: '14px' }}>
                SEND LINK
              </button>
            </form>
            
            <div style={{ marginTop: '25px', textAlign: 'center', fontSize: '13px' }}>
              <Link to="/login" style={{ color: '#d4af37', fontWeight: '700', textDecoration: 'none' }}>Back to Sign In</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ForgotPassword;
