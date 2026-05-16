import React, { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import { MdMenu, MdClose } from 'react-icons/md';

const Layout = () => {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  const toggleSidebar = () => setIsSidebarOpen(!isSidebarOpen);

  return (
    <div className="bg-premium">
      {/* Mobile Header */}
      <header className="mobile-header">
        <div className="brand" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <img 
            src="/logo.png" 
            alt="" 
            style={{ height: '20px', width: 'auto' }} 
            onError={(e) => e.target.style.display = 'none'} 
          />
          <h1 className="brand-font gold-text" style={{ fontSize: '18px', margin: 0, letterSpacing: '2px' }}>AURA</h1>
        </div>
        <button className="mobile-toggle" onClick={toggleSidebar}>
          {isSidebarOpen ? <MdClose /> : <MdMenu />}
        </button>
      </header>

      <div className="dashboard-container">
        {/* Overlay for mobile when sidebar is open */}
        {isSidebarOpen && (
          <div 
            style={{
              position: 'fixed',
              top: 0, left: 0, right: 0, bottom: 0,
              background: 'rgba(0,0,0,0.7)',
              zIndex: 999,
              backdropFilter: 'blur(4px)'
            }}
            onClick={() => setIsSidebarOpen(false)}
          />
        )}
        
        <Sidebar isOpen={isSidebarOpen} close={() => setIsSidebarOpen(false)} />
        
        <main className="main-content">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default Layout;
