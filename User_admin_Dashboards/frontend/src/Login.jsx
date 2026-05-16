import React, { useState } from 'react';
import { useAuth } from './AuthContext';
import { Link, useNavigate } from 'react-router-dom';
import { MdShield } from 'react-icons/md';

const Login = () => {
  const [loginData, setLoginData] = useState({ login: '', password: '' });
  const [error, setError] = useState('');
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    const res = await login(loginData);
    if (res.success) {
      if (res.data.user.role === 'admin') {
        navigate('/admin');
      } else {
        navigate('/dashboard');
      }
    } else {
      setError(res.message);
    }
  };

  return (
    <div className="bg-premium">
      <div className="auth-overlay">
        {/* Compact Header */}
        <header className="premium-header">
          <div className="brand" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            {/* LOGO IMAGE - LINE 33 */}
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
            <span className="nav-link">HOME</span>
            <span className="nav-link">PROMOTIONS</span>
            <span className="nav-link">SUPPORT</span>
            <span className="nav-link">CONTACT</span>
          </div>
        </header>

        <div className="auth-container">
          <div className="glass-card gold-glow">
            <div style={{ textAlign: 'center', marginBottom: '30px' }}>
              <h2 className="brand-font card-title">Sign In</h2>
              <div style={{ width: '30px', height: '2px', background: '#d4af37', margin: '0 auto 15px' }}></div>
              <p style={{ color: '#888', fontSize: '13px' }}>Access your elite dashboard</p>
            </div>
            
            {error && (
              <div style={{ 
                background: 'rgba(231, 76, 60, 0.1)', 
                color: '#e74c3c', 
                padding: '10px', 
                borderRadius: '6px', 
                marginBottom: '20px', 
                textAlign: 'center',
                fontSize: '13px',
                border: '1px solid rgba(231, 76, 60, 0.2)'
              }}>
                {error}
              </div>
            )}
            
            <form onSubmit={handleSubmit}>
              <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>USERNAME OR EMAIL</label>
                <input 
                  type="text" 
                  className="input-premium" 
                  style={{ padding: '10px', fontSize: '14px' }}
                  placeholder="Enter identifier"
                  value={loginData.login}
                  onChange={(e) => setLoginData({...loginData, login: e.target.value})}
                  required
                />
              </div>
              
              <div style={{ marginBottom: '25px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                  <label style={{ fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px', margin: 0 }}>PASSWORD</label>
                  <Link to="/forgot-password" style={{ color: '#666', textDecoration: 'none', fontSize: '10px' }}>Forgot?</Link>
                </div>
                <input 
                  type="password" 
                  className="input-premium" 
                  style={{ padding: '10px', fontSize: '14px' }}
                  placeholder="••••••••"
                  value={loginData.password}
                  onChange={(e) => setLoginData({...loginData, password: e.target.value})}
                  required
                />
              </div>
              
              <button type="submit" className="btn-primary">
                SIGN IN
              </button>
            </form>
            
            <div style={{ marginTop: '25px', textAlign: 'center', fontSize: '13px' }}>
              <p style={{ color: '#888' }}>
                New member? <Link to="/register" style={{ color: '#d4af37', fontWeight: '700', textDecoration: 'none', marginLeft: '5px' }}>Register Now</Link>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Login;
