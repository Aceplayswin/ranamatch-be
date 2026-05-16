import React, { useState } from 'react';
import { useAuth } from './AuthContext';
import { Link, useNavigate } from 'react-router-dom';
import { MdShield } from 'react-icons/md';

const Register = () => {
  const [formData, setFormData] = useState({
    full_name: '',
    username: '',
    email: '',
    mobile: '',
    password: '',
    confirm_password: ''
  });
  const [error, setError] = useState('');
  const { register } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    
    if (formData.password !== formData.confirm_password) {
      setError('Passwords do not match');
      return;
    }

    const res = await register(formData);
    if (res.success) {
      navigate('/dashboard');
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
            {/* LOGO IMAGE - LINE 42 */}
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
          <div className="glass-card register-card gold-glow">
            <div style={{ textAlign: 'center', marginBottom: '30px' }}>
              <h2 className="brand-font card-title">Register</h2>
              <div style={{ width: '30px', height: '2px', background: '#d4af37', margin: '0 auto 15px' }}></div>
              <p style={{ color: '#888', fontSize: '13px' }}>Join the elite membership</p>
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
              <div className="register-grid">
                <div style={{ marginBottom: '15px' }}>
                  <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>FULL NAME</label>
                  <input 
                    type="text" 
                    className="input-premium" 
                    placeholder="John Doe"
                    value={formData.full_name}
                    onChange={(e) => setFormData({...formData, full_name: e.target.value})}
                    required
                  />
                </div>
                <div style={{ marginBottom: '15px' }}>
                  <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>USERNAME</label>
                  <input 
                    type="text" 
                    className="input-premium" 
                    placeholder="aura_member"
                    value={formData.username}
                    onChange={(e) => setFormData({...formData, username: e.target.value})}
                    required
                  />
                </div>
              </div>

              <div className="register-grid">
                <div style={{ marginBottom: '15px' }}>
                  <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>EMAIL</label>
                  <input 
                    type="email" 
                    className="input-premium" 
                    placeholder="john@aura.com"
                    value={formData.email}
                    onChange={(e) => setFormData({...formData, email: e.target.value})}
                    required
                  />
                </div>
                <div style={{ marginBottom: '15px' }}>
                  <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>MOBILE</label>
                  <input 
                    type="text" 
                    className="input-premium" 
                    placeholder="+91 98XXX XXXXX"
                    value={formData.mobile}
                    onChange={(e) => setFormData({...formData, mobile: e.target.value})}
                    required
                  />
                </div>
              </div>
              
              <div className="register-grid">
                <div style={{ marginBottom: '20px' }}>
                  <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>PASSWORD</label>
                  <input 
                    type="password" 
                    className="input-premium" 
                    placeholder="••••••••"
                    value={formData.password}
                    onChange={(e) => setFormData({...formData, password: e.target.value})}
                    required
                  />
                </div>
                <div style={{ marginBottom: '20px' }}>
                  <label style={{ display: 'block', marginBottom: '6px', fontSize: '10px', fontWeight: '800', color: '#d4af37', letterSpacing: '1px' }}>CONFIRM</label>
                  <input 
                    type="password" 
                    className="input-premium" 
                    placeholder="••••••••"
                    value={formData.confirm_password}
                    onChange={(e) => setFormData({...formData, confirm_password: e.target.value})}
                    required
                  />
                </div>
              </div>
              
              <button type="submit" className="btn-primary">
                REGISTER NOW
              </button>
            </form>
            
            <div style={{ marginTop: '25px', textAlign: 'center', fontSize: '13px' }}>
              <p style={{ color: '#888' }}>
                Already a member? <Link to="/login" style={{ color: '#d4af37', fontWeight: '700', textDecoration: 'none', marginLeft: '5px' }}>Sign In</Link>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Register;
