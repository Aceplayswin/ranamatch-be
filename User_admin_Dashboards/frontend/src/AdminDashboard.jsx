import React, { useState, useEffect } from 'react';
import { api } from './api';
import { 
  MdPeople, 
  MdReceipt, 
  MdCheck, 
  MdClose, 
  MdRefresh, 
  MdSupervisorAccount,
  MdTrendingUp,
  MdHistory,
  MdAccountBalanceWallet,
  MdAdd,
  MdRemove
} from 'react-icons/md';

const AdminDashboard = () => {
  const [stats, setStats] = useState({ 
    total_users: 0, 
    pending_deposits: 0, 
    pending_withdrawals: 0, 
    total_deposits: 0,
    total_withdrawals: 0,
    recent_transactions: [] 
  });
  const [requests, setRequests] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  // Adjustment State
  const [adjusting, setAdjusting] = useState(null); // stores user_id
  const [adjustAmount, setAdjustAmount] = useState('');
  const [adjustType, setAdjustType] = useState('deposit');

  const fetchAdminData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const [statsRes, reqsRes, usersRes] = await Promise.all([
        api.getAdminStats(),
        api.getPendingRequests(),
        api.getAdminUsers()
      ]);
      
      if (statsRes && statsRes.success) {
        setStats(statsRes.data.stats || {});
      }
      
      if (reqsRes && reqsRes.success) {
        setRequests(reqsRes.data.requests || []);
      }

      if (usersRes && usersRes.success) {
        setUsers(usersRes.data.users || []);
      }
      
      setLoading(false);
    } catch (err) {
      console.error('Fetch error:', err);
      setError("Failed to synchronize with the secure data network.");
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAdminData();
  }, []);

  const handleAction = async (id, status) => {
    try {
      const res = await api.updateRequest(id, status);
      if (res.success) {
        fetchAdminData();
      } else {
        alert(res.message);
      }
    } catch (err) {
      alert("System error processing request");
    }
  };

  const handleAdjustBalance = async (userId) => {
    if (!adjustAmount || adjustAmount <= 0) {
      alert("Please enter a valid amount");
      return;
    }

    try {
      const res = await api.adjustBalance(userId, adjustAmount, adjustType);
      if (res.success) {
        alert("Balance adjusted successfully");
        setAdjusting(null);
        setAdjustAmount('');
        fetchAdminData();
      } else {
        alert(res.message);
      }
    } catch (err) {
      alert("Error adjusting balance");
    }
  };

  if (loading) return (
    <div className="main-content" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '80vh', flexDirection: 'column' }}>
      <div className="gold-glow" style={{ width: '40px', height: '40px', border: '3px solid #d4af37', borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 1s linear infinite', marginBottom: '20px' }}></div>
      <div style={{ color: '#d4af37', letterSpacing: '2px', fontSize: '12px', fontWeight: 'bold' }}>ESTABLISHING SECURE CONNECTION...</div>
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );

  return (
    <div className="main-content">
      <header className="dashboard-header" style={{ marginBottom: '15px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '10px' }}>
        <div>
          <h2 className="brand-font gold-text" style={{ fontSize: '20px', marginBottom: '2px' }}>AURA COMMAND</h2>
          <p style={{ color: '#555', fontSize: '10px' }}>Global Administrative Control</p>
        </div>
        <button onClick={fetchAdminData} className="btn-primary" style={{ padding: '6px 12px', fontSize: '10px', width: 'auto' }}>
          REFRESH NETWORK
        </button>
      </header>

      {/* Stats Grid */}
      <div className="stats-grid" style={{ marginBottom: '20px' }}>
        <div className="stat-card" style={{ borderLeft: '3px solid #d4af37' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <p style={{ fontSize: '10px', color: '#555', fontWeight: 'bold' }}>TOTAL MEMBERS</p>
            <MdSupervisorAccount style={{ color: '#d4af37', fontSize: '16px' }} />
          </div>
          <h3 style={{ fontSize: '24px', margin: '5px 0', color: '#fff' }}>{stats.total_users}</h3>
        </div>
        <div className="stat-card" style={{ borderLeft: '3px solid #f39c12' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <p style={{ fontSize: '10px', color: '#555', fontWeight: 'bold' }}>PENDING DEPOSITS</p>
            <MdReceipt style={{ color: '#f39c12', fontSize: '16px' }} />
          </div>
          <h3 style={{ fontSize: '24px', margin: '5px 0', color: '#fff' }}>{stats.pending_deposits}</h3>
        </div>
        <div className="stat-card" style={{ borderLeft: '3px solid #e74c3c' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <p style={{ fontSize: '10px', color: '#555', fontWeight: 'bold' }}>PENDING WITHDRAWALS</p>
            <MdReceipt style={{ color: '#e74c3c', fontSize: '16px' }} />
          </div>
          <h3 style={{ fontSize: '24px', margin: '5px 0', color: '#fff' }}>{stats.pending_withdrawals}</h3>
        </div>
        <div className="stat-card" style={{ background: 'linear-gradient(135deg, #0a0a0a 0%, #151515 100%)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <p style={{ fontSize: '10px', color: '#d4af37', fontWeight: 'bold' }}>TOTAL VOLUME</p>
            <MdTrendingUp style={{ color: '#d4af37', fontSize: '16px' }} />
          </div>
          <h3 style={{ fontSize: '22px', margin: '5px 0', color: '#fff' }}>₹ {(parseFloat(stats.total_deposits || 0) + parseFloat(stats.total_withdrawals || 0)).toLocaleString()}</h3>
        </div>
      </div>

      <div className="dashboard-grid" style={{ marginBottom: '20px' }}>
        {/* Pending Requests */}
        <div className="dashboard-card">
          <h3 className="brand-font gold-text" style={{ fontSize: '16px', marginBottom: '15px' }}>Approval Queue</h3>
          <div style={{ overflowX: 'auto' }}>
            <table className="transaction-table">
              <thead>
                <tr>
                  <th>MEMBER</th>
                  <th>TYPE</th>
                  <th>AMOUNT</th>
                  <th style={{ textAlign: 'right' }}>ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                {requests.length === 0 ? (
                  <tr><td colSpan="4" style={{ textAlign: 'center', padding: '40px', color: '#444', fontSize: '12px' }}>Queue is currently clear.</td></tr>
                ) : (
                  requests.map(r => (
                    <tr key={r.id}>
                      <td>
                        <p style={{ margin: 0, fontWeight: 'bold', fontSize: '12px' }}>{r.username}</p>
                        <p style={{ margin: 0, fontSize: '9px', color: '#555' }}>ID: {r.user_id}</p>
                      </td>
                      <td style={{ textTransform: 'uppercase', fontSize: '10px', fontWeight: 'bold', color: r.type === 'deposit' ? '#2ecc71' : '#f39c12' }}>{r.type}</td>
                      <td style={{ color: '#fff', fontWeight: 'bold', fontSize: '13px' }}>₹ {parseFloat(r.amount).toLocaleString()}</td>
                      <td style={{ textAlign: 'right' }}>
                        <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
                          <button onClick={() => handleAction(r.id, 'approve')} style={{ background: 'rgba(39, 174, 96, 0.1)', border: '1px solid rgba(39, 174, 96, 0.3)', color: '#2ecc71', padding: '5px 10px', borderRadius: '4px', cursor: 'pointer', fontSize: '10px' }}>APPROVE</button>
                          <button onClick={() => handleAction(r.id, 'reject')} style={{ background: 'rgba(231, 76, 60, 0.1)', border: '1px solid rgba(231, 76, 60, 0.3)', color: '#e74c3c', padding: '5px 10px', borderRadius: '4px', cursor: 'pointer', fontSize: '10px' }}>REJECT</button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Activity Feed */}
        <div className="dashboard-card">
          <h3 className="brand-font gold-text" style={{ fontSize: '16px', marginBottom: '15px' }}><MdHistory style={{ verticalAlign: 'middle', marginRight: '8px' }} /> Network Activity</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            {stats.recent_transactions?.slice(0, 5).map(t => (
              <div key={t.id} style={{ padding: '10px', background: 'rgba(255,255,255,0.02)', borderRadius: '8px', border: '1px solid rgba(255,255,255,0.05)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '11px' }}>
                  <span style={{ fontWeight: 'bold', color: '#fff' }}>{t.username}</span>
                  <span style={{ color: '#d4af37' }}>₹ {parseFloat(t.amount).toLocaleString()}</span>
                </div>
                <div style={{ fontSize: '9px', color: '#555', marginTop: '2px' }}>{t.type.toUpperCase()} • {t.status.toUpperCase()}</div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Member Directory & Manual Adjustment */}
      <div className="dashboard-card">
        <h3 className="brand-font gold-text" style={{ fontSize: '18px', marginBottom: '20px' }}><MdPeople style={{ verticalAlign: 'middle', marginRight: '10px' }} /> Member Directory</h3>
        <div style={{ overflowX: 'auto' }}>
          <table className="transaction-table">
            <thead>
              <tr>
                <th>MEMBER INFO</th>
                <th>ROLE</th>
                <th>BALANCE</th>
                <th>JOINED</th>
                <th style={{ textAlign: 'right' }}>FINANCIAL ADJUSTMENT</th>
              </tr>
            </thead>
            <tbody>
              {users.map(u => (
                <tr key={u.id}>
                  <td>
                    <p style={{ margin: 0, fontWeight: 'bold', fontSize: '13px', color: '#fff' }}>{u.full_name}</p>
                    <p style={{ margin: 0, fontSize: '11px', color: '#555' }}>@{u.username} • {u.email}</p>
                  </td>
                  <td>
                    <span style={{ fontSize: '10px', padding: '3px 8px', borderRadius: '4px', background: u.role === 'admin' ? 'rgba(212, 175, 55, 0.1)' : 'rgba(255,255,255,0.05)', color: u.role === 'admin' ? '#d4af37' : '#888', fontWeight: 'bold' }}>
                      {u.role.toUpperCase()}
                    </span>
                  </td>
                  <td style={{ color: '#fff', fontWeight: 'bold' }}>₹ {parseFloat(u.wallet_balance || 0).toLocaleString()}</td>
                  <td style={{ fontSize: '11px', color: '#444' }}>{new Date(u.created_at).toLocaleDateString()}</td>
                  <td style={{ textAlign: 'right' }}>
                    {adjusting === u.id ? (
                      <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end', alignItems: 'center' }}>
                        <select 
                          value={adjustType} 
                          onChange={(e) => setAdjustType(e.target.value)}
                          style={{ background: '#000', color: '#d4af37', border: '1px solid #333', borderRadius: '4px', padding: '5px', fontSize: '11px' }}
                        >
                          <option value="deposit">CREDIT (+)</option>
                          <option value="withdrawal">DEBIT (-)</option>
                        </select>
                        <input 
                          type="number" 
                          placeholder="Amount" 
                          value={adjustAmount}
                          onChange={(e) => setAdjustAmount(e.target.value)}
                          style={{ width: '80px', background: '#111', color: '#fff', border: '1px solid #333', borderRadius: '4px', padding: '5px', fontSize: '11px' }}
                        />
                        <button onClick={() => handleAdjustBalance(u.id)} style={{ background: '#d4af37', color: '#000', border: 'none', padding: '5px 10px', borderRadius: '4px', fontSize: '10px', fontWeight: 'bold', cursor: 'pointer' }}>GO</button>
                        <button onClick={() => setAdjusting(null)} style={{ background: '#222', color: '#888', border: 'none', padding: '5px 10px', borderRadius: '4px', fontSize: '10px', cursor: 'pointer' }}>CANCEL</button>
                      </div>
                    ) : (
                      <button 
                        onClick={() => setAdjusting(u.id)}
                        style={{ background: 'rgba(212, 175, 55, 0.05)', border: '1px solid rgba(212, 175, 55, 0.2)', color: '#d4af37', padding: '6px 12px', borderRadius: '4px', fontSize: '11px', fontWeight: 'bold', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '5px' }}
                      >
                        <MdAccountBalanceWallet /> ADJUST BALANCE
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;
