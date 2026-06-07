import { Link } from 'react-router-dom';
import {
  ClipboardEdit,
  Layers,
  FileCheck,
  FileText,
  GraduationCap,
  UserCog,
  SlidersHorizontal,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const ROLE_LABELS = {
  admin: 'مدير النظام',
  head: 'رئيس قسم',
  teacher: 'معلم',
  control: 'كنترول',
};

// Quick-access cards, gated by role.
const CARDS = [
  {
    to: '/grades/entry',
    title: 'رصد الدرجات',
    desc: 'إدخال درجات أعمال السنة والاختبارات',
    icon: ClipboardEdit,
    color: 'bg-blue-500',
    roles: ['teacher', 'admin'],
  },
  {
    to: '/head/dashboard',
    title: 'لوحة رئيس القسم',
    desc: 'مراجعة واعتماد درجات القسم',
    icon: Layers,
    color: 'bg-emerald-500',
    roles: ['head', 'admin'],
  },
  {
    to: '/control/dashboard',
    title: 'الكنترول',
    desc: 'المراجعة النهائية والطباعة',
    icon: FileCheck,
    color: 'bg-purple-500',
    roles: ['control', 'admin'],
  },
  {
    to: '/excuses',
    title: 'الأعذار',
    desc: 'إدارة أعذار الطلاب',
    icon: FileText,
    color: 'bg-amber-500',
    roles: ['teacher', 'head', 'control', 'admin'],
  },
  {
    to: '/students',
    title: 'الطلاب',
    desc: 'إدارة بيانات الطلاب',
    icon: GraduationCap,
    color: 'bg-rose-500',
    roles: ['admin'],
  },
  {
    to: '/users',
    title: 'المستخدمون',
    desc: 'إدارة حسابات المستخدمين',
    icon: UserCog,
    color: 'bg-cyan-500',
    roles: ['admin'],
  },
  {
    to: '/admin/settings',
    title: 'إعدادات الرصد',
    desc: 'ضبط الفترات والأعمدة والصلاحيات',
    icon: SlidersHorizontal,
    color: 'bg-indigo-500',
    roles: ['admin'],
  },
];

const Dashboard = () => {
  const { user } = useAuth();
  const cards = CARDS.filter((c) => c.roles.includes(user?.role));

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-800">
          مرحباً، {user?.name} 👋
        </h1>
        <p className="text-gray-500 mt-1">
          {ROLE_LABELS[user?.role] || user?.role}
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        {cards.map((card) => (
          <Link
            key={card.to}
            to={card.to}
            className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-blue-300 transition-all group"
          >
            <div
              className={`w-12 h-12 ${card.color} rounded-lg flex items-center justify-center mb-4 group-hover:scale-105 transition-transform`}
            >
              <card.icon className="w-6 h-6 text-white" />
            </div>
            <h3 className="text-lg font-bold text-gray-800 mb-1">{card.title}</h3>
            <p className="text-sm text-gray-500">{card.desc}</p>
          </Link>
        ))}
      </div>

      {cards.length === 0 && (
        <div className="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-500">
          لا توجد أقسام متاحة لحسابك حالياً.
        </div>
      )}
    </div>
  );
};

export default Dashboard;
