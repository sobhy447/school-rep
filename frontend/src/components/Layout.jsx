import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import {
  Home,
  ClipboardEdit,
  Layers,
  FileCheck,
  FileText,
  GraduationCap,
  UserCog,
  SlidersHorizontal,
  Settings as SettingsIcon,
  LogOut,
  Menu,
  X,
  School,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const ROLE_LABELS = {
  admin: 'مدير النظام',
  head: 'رئيس قسم',
  teacher: 'معلم',
  control: 'كنترول',
};

// Navigation entries gated by role. Admin sees everything.
const NAV_ITEMS = [
  { to: '/dashboard', label: 'الرئيسية', icon: Home, roles: ['admin', 'head', 'teacher', 'control'] },
  { to: '/grades/entry', label: 'رصد الدرجات', icon: ClipboardEdit, roles: ['teacher', 'admin'] },
  { to: '/head/dashboard', label: 'لوحة رئيس القسم', icon: Layers, roles: ['head', 'admin'] },
  { to: '/control/dashboard', label: 'الكنترول', icon: FileCheck, roles: ['control', 'admin'] },
  { to: '/excuses', label: 'الأعذار', icon: FileText, roles: ['teacher', 'head', 'control', 'admin'] },
  { to: '/students', label: 'الطلاب', icon: GraduationCap, roles: ['admin'] },
  { to: '/users', label: 'المستخدمون', icon: UserCog, roles: ['admin'] },
  { to: '/admin/settings', label: 'إعدادات الرصد', icon: SlidersHorizontal, roles: ['admin'] },
  { to: '/settings', label: 'الإعدادات', icon: SettingsIcon, roles: ['admin'] },
];

const Layout = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [mobileOpen, setMobileOpen] = useState(false);

  const handleLogout = () => {
    logout();
    navigate('/login', { replace: true });
  };

  const visibleItems = NAV_ITEMS.filter((item) => item.roles.includes(user?.role));

  const navLinkClass = ({ isActive }) =>
    `flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors ${
      isActive
        ? 'bg-blue-600 text-white'
        : 'text-gray-600 hover:bg-blue-50 hover:text-blue-700'
    }`;

  const sidebarContent = (
    <>
      <div className="flex items-center gap-3 px-2 py-4 border-b border-gray-100">
        <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
          <School className="w-6 h-6 text-white" />
        </div>
        <div>
          <h1 className="text-base font-bold text-gray-800">نظام المدرسة</h1>
          <p className="text-xs text-gray-500">إدارة الدرجات</p>
        </div>
      </div>

      <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
        {visibleItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={navLinkClass}
            onClick={() => setMobileOpen(false)}
          >
            <item.icon className="w-5 h-5 shrink-0" />
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>

      <div className="px-2 py-4 border-t border-gray-100">
        <div className="px-2 mb-3">
          <p className="text-sm font-semibold text-gray-800 truncate">{user?.name}</p>
          <p className="text-xs text-gray-500">{ROLE_LABELS[user?.role] || user?.role}</p>
        </div>
        <button
          onClick={handleLogout}
          className="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
        >
          <LogOut className="w-5 h-5" />
          <span>تسجيل الخروج</span>
        </button>
      </div>
    </>
  );

  return (
    <div className="min-h-screen bg-gray-50 flex">
      {/* Desktop sidebar */}
      <aside className="hidden lg:flex w-64 bg-white border-l border-gray-200 flex-col shrink-0">
        {sidebarContent}
      </aside>

      {/* Mobile sidebar */}
      {mobileOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div
            className="absolute inset-0 bg-black/40"
            onClick={() => setMobileOpen(false)}
          />
          <aside className="absolute right-0 top-0 h-full w-64 bg-white border-l border-gray-200 flex flex-col shadow-xl">
            {sidebarContent}
          </aside>
        </div>
      )}

      <div className="flex-1 flex flex-col min-w-0">
        {/* Mobile top bar */}
        <header className="lg:hidden bg-white border-b border-gray-200 flex items-center justify-between px-4 py-3">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
              <School className="w-5 h-5 text-white" />
            </div>
            <span className="font-bold text-gray-800">نظام المدرسة</span>
          </div>
          <button
            onClick={() => setMobileOpen((v) => !v)}
            className="p-2 text-gray-600 hover:text-gray-800"
            aria-label="القائمة"
          >
            {mobileOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </header>

        <main className="flex-1 overflow-y-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default Layout;
