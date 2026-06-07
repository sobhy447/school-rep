import { useState, useEffect, useCallback } from 'react';
import { GraduationCap, Loader2, AlertCircle, Search } from 'lucide-react';
import api from '../services/api';

const Students = () => {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [query, setQuery] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await api.get('/students');
      setStudents(Array.isArray(data) ? data : data.data || []);
    } catch (err) {
      if (err.response?.status === 404) {
        setError('خدمة الطلاب غير متاحة على الخادم حالياً.');
      } else {
        setError(err.response?.data?.message || 'تعذر تحميل بيانات الطلاب');
      }
      setStudents([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const filtered = students.filter((s) =>
    (s.name || '').toLowerCase().includes(query.toLowerCase())
  );

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-rose-500 rounded-lg flex items-center justify-center">
          <GraduationCap className="w-6 h-6 text-white" />
        </div>
        <h1 className="text-2xl font-bold text-gray-800">الطلاب</h1>
      </div>

      <div className="relative mb-5 max-w-sm">
        <Search className="w-5 h-5 text-gray-400 absolute top-1/2 -translate-y-1/2 right-3" />
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="بحث باسم الطالب..."
          className="w-full pr-10 pl-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
        />
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
      ) : filtered.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-500">
          لا توجد بيانات طلاب لعرضها.
        </div>
      ) : (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="text-right px-4 py-3 font-medium">الاسم</th>
                <th className="text-right px-4 py-3 font-medium">الرقم المدني</th>
                <th className="text-right px-4 py-3 font-medium">الفصل</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {filtered.map((s) => (
                <tr key={s._id || s.civilId} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-gray-800">{s.name}</td>
                  <td className="px-4 py-3 text-gray-600">{s.civilId || '—'}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {s.class?.name || s.className || '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

export default Students;
