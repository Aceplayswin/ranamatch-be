const API_BASE = '/api';

export async function apiRequest(endpoint, options = {}) {
  const token = localStorage.getItem('token');
  
  const config = {
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...options.headers,
    },
    ...options,
  };

  const response = await fetch(`${API_BASE}${endpoint}`, config);
  const text = await response.text();
  
  try {
    return JSON.parse(text);
  } catch (err) {
    console.error('API Error Response:', text);
    return { success: false, message: 'Server error: Invalid response format' };
  }
}

export const api = {
  login: (credentials) => apiRequest('/login.php', {
    method: 'POST',
    body: JSON.stringify(credentials),
  }),
  
  register: (userData) => apiRequest('/register.php', {
    method: 'POST',
    body: JSON.stringify(userData),
  }),
  
  getUser: () => apiRequest('/get_user.php'),
  
  // User wallet
  getWallet: () => apiRequest('/user/wallet.php'),
  
  submitRequest: (type, amount) => apiRequest('/user/wallet.php', {
    method: 'POST',
    body: JSON.stringify({ type, amount }),
  }),
  
  // Admin Dashboard
  getAdminStats: () => apiRequest('/admin/dashboard.php'),
  
  getPendingRequests: () => apiRequest('/admin/requests.php?status=pending'),
  
  updateRequest: (id, status) => apiRequest('/admin/requests.php', {
    method: 'POST',
    body: JSON.stringify({ transaction_id: id, action: status }),
  }),
  
  getAdminUsers: () => apiRequest('/admin/users.php'),
  
  adjustBalance: (userId, amount, type) => apiRequest('/admin/adjust_balance.php', {
    method: 'POST',
    body: JSON.stringify({ user_id: userId, amount, type }),
  }),
};
