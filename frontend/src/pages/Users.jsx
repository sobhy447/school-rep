import { useState, useEffect, useCallback } from 'react';
import { UserCog, Loader2, AlertCircle } from 'lucide-react';
import api from '../services/api';

const ROLE_LABELS = {
  admin: 'مدير النظام',
  head: 'رئيس قسم',
  teacher: 'معلم',
  control: 'كنترول',
};

const ROLE_BADGE = {
  admin: 'bg-indigo-100 text-indigo-700',
  head: 'bg-emerald-100 text-emerald-700',
  teacher: 'bg-blue-100 text-blue-700',
  control: 'bg-purple-100 text-purple-700',
};

const Users = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await api.get('/users');
      setUsers(Array.isArray(data) ? data : data.data || []);
    } catch (err) {
      if (err.response?.status === 404) {
        setError('خدمة المستخدمين غير متاحة على الخادم حالياً.');
      } else {
        setError(err.response?.data?.message || 'تعذر تحميل بيانات المستخدمين');
      }
      setUsers([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-cyan-500 rounded-lg flex items-center justify-center">
          <UserCog className="w-6 h-6 text-white" />
        </div>
        <h1 className="text-2xl font-bold text-gray-800">المستخدمون</h1>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-16 text-gray-500">
          <Loader2 className="w-6 h-6 animate-spin ml-2" />
          جارِ التحميل...
        </div>
      ) : error ? (
        <div className="p-4 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-3 text-amber-700">
          <AlertCircle className="w-5 h-5 shrink-0" />
          <span>{error}</span>
        </div>
      ) : users.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-500">
          لا توجد بيانات مستخدمين لعرضها.
        </div>
      ) : (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="text-right px-4 py-3 font-medium">الاسم</th>
                <th className="text-right px-4 py-3 font-medium">الرقم المدني</th>
                <th className="text-right px-4 py-3 font-medium">الدور</th>
                <th className="text-right px-4 py-3 font-medium">القسم</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {users.map((u) => (
                <tr key={u._id || u.civilId} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-gray-800">{u.name}</td>
                  <td className="px-4 py-3 text-gray-600">{u.civilId || '—'}</td>
                  <td className="px-4 py-3">
                    <span
                      className={`inline-block px-2.5 py-1 rounded-full text-xs font-medium ${
                        ROLE_BADGE[u.role] || 'bg-gray-100 text-gray-700'
                      }`}
                    >
                      {ROLE_LABELS[u.role] || u.role}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-600">{u.department || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

export default Users;
