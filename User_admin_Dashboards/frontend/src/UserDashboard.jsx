import React, { useState, useEffect } from 'react';
import { api } from './api';
import { useAuth } from './AuthContext';
import { MdTrendingUp, MdAccountBalanceWallet, MdSend, MdCallReceived, MdHistory } from 'react-icons/md';

const UserDashboard = () => {
  const { user } = useAuth();
  const [wallet, setWallet] = useState({ balance: 0, transactions: [], totals: {} });
  const [amount, setAmount] = useState('');
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState({ type: '', text: '' });

  const fetchWallet = async () => {
    const res = await api.getWallet();
    if (res.success) {
      setWallet(res.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    fetchWallet();
  }, []);

  const handleRequest = async (type) => {
    if (!amount || isNaN(amount) || amount <= 0) {
      setMessage({ type: 'error', text: 'Please enter a valid amount' });
      return;
    }
    
    const res = await api.submitRequest(type, amount);
    if (res.success) {
      setMessage({ type: 'success', text: res.message });
      setAmount('');
      fetchWallet();
    } else {
      setMessage({ type: 'error', text: res.message });
    }
  };

  if (loading) return <div style={{ padding: '20px', color: '#d4af37' }}>Authenticating Elite Access...</div>;

  return (
    <div className="main-content">
      <header className="dashboard-header" style={{ marginBottom: '25px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '15px' }}>
        <div>
          <h2 className="brand-font gold-text" style={{ fontSize: '24px', marginBottom: '2px' }}>Member Overview</h2>
          <p style={{ color: '#555', fontSize: '11px' }}>Welcome back, <span style={{ color: '#fff' }}>{user?.full_name}</span> • ID: ASURA-{user?.id}</p>
        </div>
        <div style={{ textAlign: 'right' }}>
          <p style={{ fontSize: '9px', color: '#d4af37', fontWeight: 'bold', letterSpacing: '1px' }}>STATUS</p>
          <span style={{ background: 'rgba(39, 174, 96, 0.1)', color: '#2ecc71', padding: '3px 10px', borderRadius: '15px', fontSize: '9px', border: '1px solid rgba(39, 174, 96, 0.2)' }}>VERIFIED ELITE</span>
        </div>
      </header>

      {message.text && (
        <div style={{ 
          padding: '15px', 
          borderRadius: '12px', 
          marginBottom: '25px', 
          background: message.type === 'success' ? 'rgba(39, 174, 96, 0.1)' : 'rgba(231, 76, 60, 0.1)',
          color: message.type === 'success' ? '#2ecc71' : '#e74c3c',
          border: `1px solid ${message.type === 'success' ? 'rgba(39, 174, 96, 0.2)' : 'rgba(231, 76, 60, 0.2)'}`,
          textAlign: 'center',
          fontSize: '14px'
        }}>
          {message.text}
        </div>
      )}

      <div className="stats-grid" style={{ marginBottom: '25px' }}>
        <div className="stat-card" style={{ background: 'linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%)', border: '1px solid rgba(212, 175, 55, 0.3)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <p style={{ fontSize: '10px', color: '#d4af37', fontWeight: 'bold', letterSpacing: '1px' }}>TOTAL BALANCE</p>
            <MdAccountBalanceWallet style={{ color: '#d4af37', fontSize: '16px' }} />
          </div>
          <h3 style={{ fontSize: '24px', margin: '10px 0', color: '#fff' }}>₹ {parseFloat(wallet.balance).toLocaleString()}</h3>
          <div style={{ width: '100%', height: '3px', background: 'rgba(212, 175, 55, 0.1)', borderRadius: '2px' }}>
            <div style={{ width: '70%', height: '100%', background: 'var(--primary-gold)', borderRadius: '2px' }}></div>
          </div>
        </div>
        
        <div className="stat-card">
          <p style={{ fontSize: '10px', color: '#888', fontWeight: 'bold', letterSpacing: '1px' }}>TOTAL DEPOSITS</p>
          <h3 style={{ fontSize: '22px', margin: '8px 0', color: '#fff' }}>₹ {parseFloat(wallet.totals.total_deposits || 0).toLocaleString()}</h3>
          <p style={{ fontSize: '10px', color: '#2ecc71' }}>+ Active Growth</p>
        </div>
        
        <div className="stat-card">
          <p style={{ fontSize: '10px', color: '#888', fontWeight: 'bold', letterSpacing: '1px' }}>TOTAL WITHDRAWALS</p>
          <h3 style={{ fontSize: '22px', margin: '8px 0', color: '#fff' }}>₹ {parseFloat(wallet.totals.total_withdrawals || 0).toLocaleString()}</h3>
          <p style={{ fontSize: '10px', color: '#e74c3c' }}>- Settlement History</p>
        </div>

        <div className="stat-card" style={{ background: 'rgba(212, 175, 55, 0.05)' }}>
           <p style={{ fontSize: '10px', color: '#d4af37', fontWeight: 'bold', letterSpacing: '1px' }}>REFERRAL CODE</p>
           <h3 style={{ fontSize: '16px', margin: '8px 0', color: '#fff', letterSpacing: '1px' }}>ASR-{user?.id}X-GOLD</h3>
           <button style={{ background: 'transparent', border: '1px solid #d4af37', color: '#d4af37', fontSize: '9px', padding: '4px 10px', borderRadius: '4px', cursor: 'pointer' }}>COPY CODE</button>
        </div>
      </div>

      <div className="dashboard-grid">
        <div className="dashboard-card">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '30px' }}>
            <h3 className="brand-font gold-text" style={{ fontSize: '22px' }}><MdHistory style={{ verticalAlign: 'middle', marginRight: '10px' }} /> Transaction History</h3>
            <span style={{ fontSize: '11px', color: '#555', cursor: 'pointer' }}>View All</span>
          </div>
          <table className="transaction-table">
            <thead>
              <tr>
                <th>DATE</th>
                <th>DESCRIPTION</th>
                <th>AMOUNT</th>
                <th>STATUS</th>
              </tr>
            </thead>
            <tbody>
              {wallet.transactions.length === 0 ? (
                <tr><td colSpan="4" style={{ textAlign: 'center', padding: '60px', color: '#444' }}>No transaction history available.</td></tr>
              ) : (
                wallet.transactions.map(t => (
                  <tr key={t.id}>
                    <td style={{ color: '#888' }}>{new Date(t.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                    <td style={{ fontWeight: '500' }}>{t.type === 'deposit' ? 'Capital Deposit' : 'Wealth Withdrawal'}</td>
                    <td style={{ fontWeight: 'bold', color: t.type === 'deposit' ? '#2ecc71' : '#e74c3c' }}>{t.type === 'deposit' ? '+' : '-'} ₹ {parseFloat(t.amount).toLocaleString()}</td>
                    <td>
                      <span style={{ 
                        fontSize: '10px', 
                        padding: '4px 10px', 
                        borderRadius: '4px', 
                        background: t.status === 'approved' ? 'rgba(39, 174, 96, 0.1)' : (t.status === 'pending' ? 'rgba(243, 156, 18, 0.1)' : 'rgba(231, 76, 60, 0.1)'),
                        color: t.status === 'approved' ? '#2ecc71' : (t.status === 'pending' ? '#f39c12' : '#e74c3c'),
                        textTransform: 'uppercase',
                        fontWeight: 'bold',
                        border: `1px solid ${t.status === 'approved' ? 'rgba(39, 174, 96, 0.2)' : (t.status === 'pending' ? 'rgba(243, 156, 18, 0.2)' : 'rgba(231, 76, 60, 0.2)')}`
                      }}>
                        {t.status}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
          <div className="dashboard-card" style={{ border: '1px solid rgba(212, 175, 55, 0.2)' }}>
            <h3 className="brand-font gold-text" style={{ marginBottom: '25px', fontSize: '20px' }}>Quick Actions</h3>
            <div style={{ marginBottom: '20px' }}>
              <label style={{ display: 'block', fontSize: '10px', fontWeight: 'bold', color: '#555', marginBottom: '8px', letterSpacing: '1px' }}>ENTER AMOUNT (₹)</label>
              <input 
                type="number" 
                className="input-premium" 
                style={{ marginBottom: '0' }}
                placeholder="0.00"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px' }}>
              <button onClick={() => handleRequest('deposit')} className="btn-primary" style={{ fontSize: '12px', padding: '15px 0' }}>
                <MdSend style={{ marginRight: '8px' }} /> DEPOSIT
              </button>
              <button onClick={() => handleRequest('withdrawal')} className="btn-primary" style={{ background: '#000', color: '#d4af37', border: '1px solid #d4af37', fontSize: '12px', padding: '15px 0' }}>
                <MdCallReceived style={{ marginRight: '8px' }} /> WITHDRAW
              </button>
            </div>
          </div>

          <div className="dashboard-card" style={{ background: 'linear-gradient(45deg, #0a0a0a 0%, #1a1a1a 100%)', border: '1px solid rgba(212, 175, 55, 0.1)' }}>
            <h4 className="gold-text" style={{ fontSize: '16px', marginBottom: '10px' }}>Premium Concierge</h4>
            <p style={{ fontSize: '12px', color: '#666', lineHeight: '1.8' }}>Our elite support desk is available 24/7 to handle your high-value requests.</p>
            <button style={{ marginTop: '20px', width: '100%', background: 'transparent', border: '1px solid #333', color: '#888', padding: '12px', borderRadius: '8px', fontSize: '12px', cursor: 'pointer' }}>START SECURE CHAT</button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default UserDashboard;
