import React, { useState } from 'react';
import { Trash2, AlertCircle, Search, Filter, Lock, BookOpen, Calendar } from 'lucide-react';

const ExcuseList = ({ excuses, onDelete, loading }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [filterType, setFilterType] = useState('all');
  const [filterPeriod, setFilterPeriod] = useState('all');
  const [showConfirm, setShowConfirm] = useState(null);

  // Filter excuses
  const filteredExcuses = excuses.filter(excuse => {
    const matchesSearch = 
      excuse.studentName?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      excuse.className?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      excuse.subjectName?.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesType = filterType === 'all' || excuse.type === filterType;
    const matchesPeriod = filterPeriod === 'all' || excuse.period === filterPeriod;

    return matchesSearch && matchesType && matchesPeriod;
  });

  const getTypeLabel = (type) => {
    return type === 'work' ? 'الأعمال' : 'الاختبار';
  };

  const getPeriodLabel = (period) => {
    return period === 'first' ? 'الأولى' : 'الثانية';
  };

  const getTypeColor = (type) => {
    return type === 'work' 
      ? 'bg-blue-100 text-blue-700 border-blue-200' 
      : 'bg-purple-100 text-purple-700 border-purple-200';
  };

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
      {/* Header */}
      <div className="p-4 border-b border-gray-200">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-bold text-gray-800">قائمة الاعذار</h3>
          <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm">
            {filteredExcuses.length} عذر
          </span>
        </div>

        {/* Filters */}
        <div className="flex flex-wrap gap-3">
          {/* Search */}
          <div className="flex-1 min-w-[200px]">
            <div className="relative">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="بحث بالطالب أو الفصل أو المادة..."
                className="w-full pr-10 pl-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
          </div>

          {/* Type Filter */}
          <div className="flex items-center gap-2">
            <Filter className="w-4 h-4 text-gray-400" />
            <select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">كل الأنواع</option>
              <option value="work">الأعمال</option>
              <option value="exam">الاختبار</option>
            </select>
          </div>

          {/* Period Filter */}
          <div className="flex items-center gap-2">
            <Calendar className="w-4 h-4 text-gray-400" />
            <select
              value={filterPeriod}
              onChange={(e) => setFilterPeriod(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">كل الفترات</option>
              <option value="first">الأولى</option>
              <option value="second">الثانية</option>
            </select>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 text-gray-700">
              <th className="px-4 py-3 text-right font-semibold">الطالب</th>
              <th className="px-4 py-3 text-right font-semibold">الفصل</th>
              <th className="px-4 py-3 text-right font-semibold">المادة</th>
              <th className="px-4 py-3 text-center font-semibold">الفترة</th>
              <th className="px-4 py-3 text-center font-semibold">النوع</th>
              <th className="px-4 py-3 text-right font-semibold">السبب</th>
              <th className="px-4 py-3 text-center font-semibold">الإجراء</th>
            </tr>
          </thead>
          <tbody>
            {filteredExcuses.map((excuse, index) => (
              <tr 
                key={excuse._id} 
                className={`border-b transition-colors hover:bg-gray-50
                  ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}
                `}
              >
                <td className="px-4 py-3">
                  <div className="font-medium text-gray-800">{excuse.studentName}</div>
                  <div className="text-xs text-gray-500">{excuse.studentId}</div>
                </td>
                <td className="px-4 py-3 text-gray-700">{excuse.className}</td>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-1">
                    <BookOpen className="w-3 h-3 text-gray-400" />
                    <span className="text-gray-700">{excuse.subjectName}</span>
                  </div>
                </td>
                <td className="px-4 py-3 text-center">
                  <span className="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">
                    {getPeriodLabel(excuse.period)}
                  </span>
                </td>
                <td className="px-4 py-3 text-center">
                  <span className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium border
                    ${getTypeColor(excuse.type)}
                  `}>
                    <Lock className="w-3 h-3" />
                    {getTypeLabel(excuse.type)}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <div className="max-w-[200px] truncate text-gray-600" title={excuse.reason}>
                    {excuse.reason}
                  </div>
                </td>
                <td className="px-4 py-3">
                  <div className="flex items-center justify-center">
                    <button
                      onClick={() => setShowConfirm(excuse._id)}
                      className="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                      title="حذف العذر"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Empty State */}
      {filteredExcuses.length === 0 && !loading && (
        <div className="text-center py-12 text-gray-500">
          <AlertCircle className="w-12 h-12 mx-auto mb-3 text-gray-300" />
          <p>لا توجد اعذار</p>
          <p className="text-sm mt-1">أضف عذر جديد من النموذج أعلاه</p>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="text-center py-12">
          <div className="animate-spin w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full mx-auto mb-4"></div>
          <p className="text-gray-600">جاري التحميل...</p>
        </div>
      )}

      {/* Confirm Delete Modal */}
      {showConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <div className="flex items-center gap-3 mb-4 text-red-600">
              <AlertCircle className="w-8 h-8" />
              <h3 className="text-lg font-bold">تأكيد الحذف</h3>
            </div>
            <p className="text-gray-600 mb-6">
              هل أنت متأكد من حذف هذا العذر؟
              <br />
              <span className="text-red-500 text-sm">
                بعد الحذف، يمكن رصد الدرجة للطالب مرة أخرى.
              </span>
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowConfirm(null)}
                className="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              >
                إلغاء
              </button>
              <button
                onClick={() => {
                  onDelete(showConfirm);
                  setShowConfirm(null);
                }}
                className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
              >
                حذف
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ExcuseList;
