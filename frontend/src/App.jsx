import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { SettingsProvider } from './context/SettingsContext';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Students from './pages/Students';
import Users from './pages/Users';
import Settings from './pages/Settings';
import TeacherGradeEntry from './pages/teacher/GradeEntry';
import HeadDashboard from './pages/head/HeadDashboard';
import ControlDashboard from './pages/control/ControlDashboard';
import ExcuseManager from './pages/excuses/ExcuseManager';
import AdminSettings from './pages/admin/AdminSettings';
import './index.css';

function App() {
  return (
    <AuthProvider>
      <SettingsProvider>
        <Router>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/" element={<Navigate to="/dashboard" replace />} />

            <Route element={<ProtectedRoute />}>
              <Route element={<Layout />}>
                <Route path="/dashboard" element={<Dashboard />} />
                <Route path="/students" element={<Students />} />
                <Route path="/users" element={<Users />} />
                <Route path="/settings" element={<Settings />} />
                <Route path="/grades/entry" element={<TeacherGradeEntry />} />
                <Route path="/head/dashboard" element={<HeadDashboard />} />
                <Route path="/control/dashboard" element={<ControlDashboard />} />
                <Route path="/excuses" element={<ExcuseManager />} />
                <Route path="/admin/settings" element={<AdminSettings />} />
              </Route>
            </Route>
          </Routes>
        </Router>
      </SettingsProvider>
    </AuthProvider>
  );
}

export default App;
