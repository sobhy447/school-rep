import { Settings as SettingsIcon, User, Shield, SlidersHorizontal } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useSettings } from '../context/SettingsContext';

const ROLE_LABELS = {
  admin: 'مدير النظام',
  head: 'رئيس قسم',
  teacher: 'معلم',
  control: 'كنترول',
};

const Row = ({ label, value }) => (
  <div className="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
    <span className="text-sm text-gray-500">{label}</span>
    <span className="text-sm font-medium text-gray-800">{value}</span>
  </div>
);

const Settings = () => {
  const { user } = useAuth();
  const { settings } = useSettings();

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-gray-700 rounded-lg flex items-center justify-center">
          <SettingsIcon className="w-6 h-6 text-white" />
        </div>
        <h1 className="text-2xl font-bold text-gray-800">الإعدادات</h1>
      </div>

      <div className="space-y-6">
        <section className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center gap-2 mb-3 text-gray-700">
            <User className="w-5 h-5" />
            <h2 className="font-bold">الملف الشخصي</h2>
          </div>
          <Row label="الاسم" value={user?.name || '—'} />
          <Row label="البريد الإلكتروني" value={user?.email || '—'} />
          <Row label="الدور" value={ROLE_LABELS[user?.role] || user?.role || '—'} />
          {user?.department && <Row label="القسم" value={user.department} />}
        </section>

        <section className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center gap-2 mb-3 text-gray-700">
            <Shield className="w-5 h-5" />
            <h2 className="font-bold">إعدادات النظام الحالية</h2>
          </div>
          <Row label="اسم المدرسة" value={settings.schoolName} />
          <Row label="درجة أعمال السنة العظمى" value={settings.workMax} />
          <Row label="درجة الاختبار العظمى" value={settings.examMax} />
          <Row label="السماح بأنصاف الدرجات" value={settings.allowHalf ? 'نعم' : 'لا'} />
        </section>

        {user?.role === 'admin' && (
          <Link
            to="/admin/settings"
            className="flex items-center justify-center gap-2 bg-blue-600 text-white font-medium py-2.5 rounded-lg hover:bg-blue-700 transition-colors"
          >
            <SlidersHorizontal className="w-5 h-5" />
            <span>الانتقال إلى إعدادات الرصد المتقدمة</span>
          </Link>
        )}
      </div>
    </div>
  );
};

export default Settings;
