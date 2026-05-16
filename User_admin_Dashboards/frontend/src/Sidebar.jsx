import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from './AuthContext';
import { 
  MdDashboard, 
  MdPeople, 
  MdReceipt, 
  MdAccountBalanceWallet, 
  MdVerifiedUser, 
  MdSettings,
  MdExitToApp,
  MdSupervisorAccount,
  MdShield
} from 'react-icons/md';

const Sidebar = ({ isOpen, close }) => {
  const { user, logout } = useAuth();
  
  if (!user) return null;
  const isAdmin = user.role === 'admin';

  return (
    <div className={`sidebar ${isOpen ? 'open' : ''}`} style={{ display: 'flex', flexDirection: 'column' }}>
      {/* Brand Section */}
      <div className="brand" style={{ padding: '15px 20px', borderBottom: '1px solid rgba(212, 175, 55, 0.1)', textAlign: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', marginBottom: '4px' }}>
          <img 
            src="/logo.png" 
            alt="" 
            style={{ height: '22px', width: 'auto', display: 'block' }} 
            onError={(e) => e.target.style.display = 'none'} 
          />
          <h1 className="brand-font gold-text" style={{ fontSize: '20px', margin: 0, letterSpacing: '3px' }}>AURA</h1>
        </div>
        <p style={{ fontSize: '8px', color: '#555', letterSpacing: '2px', margin: 0 }}>PREMIUM HUB</p>
      </div>

      {/* Main Menu - Flexible & Scrollable */}
      <div style={{ flex: 1, overflowY: 'auto', padding: '10px 0' }}>
        <div style={{ padding: '0 20px 8px', fontSize: '9px', color: '#444', fontWeight: 'bold', letterSpacing: '1px' }}>MAIN MENU</div>
        {isAdmin ? (
          <>
            <NavLink to="/admin" className="sidebar-link" onClick={close} end>
              <MdDashboard style={{ fontSize: '18px' }} /> Overview
            </NavLink>
            <NavLink to="/admin/approvals" className="sidebar-link" onClick={close}>
              <MdReceipt style={{ fontSize: '18px' }} /> Approvals
            </NavLink>
            <NavLink to="/admin/members" className="sidebar-link" onClick={close}>
              <MdSupervisorAccount style={{ fontSize: '18px' }} /> Members
            </NavLink>
          </>
        ) : (
          <>
            <NavLink to="/dashboard" className="sidebar-link" onClick={close} end>
              <MdDashboard style={{ fontSize: '18px' }} /> Dashboard
            </NavLink>
            <div className="sidebar-link" style={{ opacity: 0.3, cursor: 'not-allowed' }}>
              <MdPeople style={{ fontSize: '18px' }} /> Referrals
            </div>
            <div className="sidebar-link" style={{ opacity: 0.3, cursor: 'not-allowed' }}>
              <MdReceipt style={{ fontSize: '18px' }} /> History
            </div>
            <div className="sidebar-link" style={{ opacity: 0.3, cursor: 'not-allowed' }}>
              <MdAccountBalanceWallet style={{ fontSize: '18px' }} /> Wallet
            </div>
            <div className="sidebar-link" style={{ opacity: 0.3, cursor: 'not-allowed' }}>
              <MdVerifiedUser style={{ fontSize: '18px' }} /> KYC
            </div>
            <div className="sidebar-link" style={{ opacity: 0.3, cursor: 'not-allowed' }}>
              <MdSettings style={{ fontSize: '18px' }} /> Settings
            </div>
          </>
        )}
      </div>

      {/* Pinned Profile & Logout Section */}
      <div style={{ 
        background: 'linear-gradient(to top, #000, #050505)', 
        borderTop: '1px solid rgba(212, 175, 55, 0.1)',
        padding: '12px 15px',
        marginTop: 'auto'
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '10px' }}>
          <div style={{ 
            width: '32px', 
            height: '32px', 
            borderRadius: '8px', 
            background: 'linear-gradient(135deg, #d4af37, #c5a028)', 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'center',
            color: '#000',
            fontWeight: '900',
            fontSize: '14px'
          }}>
            {user.full_name ? user.full_name.charAt(0).toUpperCase() : 'A'}
          </div>
          <div style={{ overflow: 'hidden' }}>
            <p style={{ fontSize: '12px', fontWeight: 'bold', color: '#fff', margin: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{user.full_name}</p>
            <p style={{ fontSize: '9px', color: '#d4af37', margin: 0, textTransform: 'uppercase', fontWeight: 'bold' }}>{user.role} SESSION</p>
          </div>
        </div>
        <button 
          onClick={logout} 
          className="sidebar-link" 
          style={{ 
            width: '100%', 
            background: 'rgba(212, 175, 55, 0.05)', 
            border: '1px solid rgba(212, 175, 55, 0.15)', 
            cursor: 'pointer', 
            justifyContent: 'center',
            borderRadius: '8px',
            color: '#d4af37',
            padding: '8px',
            fontSize: '11px',
            fontWeight: 'bold',
            transition: '0.3s'
          }}
          onMouseOver={(e) => e.currentTarget.style.background = 'rgba(212, 175, 55, 0.15)'}
          onMouseOut={(e) => e.currentTarget.style.background = 'rgba(212, 175, 55, 0.05)'}
        >
          <MdExitToApp style={{ fontSize: '16px' }} /> LOGOUT
        </button>
      </div>
    </div>
  );
};

export default Sidebar;
