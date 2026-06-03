import React, { useState } from 'react';
import { Unlock, AlertCircle, Search, Filter, CheckCircle, User, BookOpen, Calendar } from 'lucide-react';

const ApprovalUnlock = ({ approvals, classes, subjects, onUnlock, loading }) => {
  const [filters, setFilters] = useState({
    classId: '',
    subjectId: '',
    period: 'all',
    level: 'all' // teacher, head, all
  });
  const [showConfirm, setShowConfirm] = useState(null);
  const [unlockType, setUnlockType] = useState('all'); // work, exam, all

  // Filter approvals
  const filteredApprovals = approvals.filter(approval => {
    if (filters.classId && approval.classId !== filters.classId) return false;
    if (filters.subjectId && approval.subjectId !== filters.subjectId) return false;
    if (filters.period !== 'all' && approval.period !== filters.period) return false;
    if (filters.level !== 'all' && approval.level !== filters.level) return false;
    return true;
  });

  const getLevelLabel = (level) => {
    return level === 'teacher' ? 'اعتماد المعلم' : 'اعتماد رئيس القسم';
  };

  const getLevelColor = (level) => {
    return level === 'teacher' 
      ? 'bg-blue-100 text-blue-700 border-blue-200' 
      : 'bg-purple-100 text-purple-700 border-purple-200';
  };

  const getStatusIcon = (status) => {
    return status === 'approved' 
      ? <CheckCircle className="w-4 h-4 text-green-600" />
      : <AlertCircle className="w-4 h-4 text-yellow-600" />;
  };

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
      {/* Header */}
      <div className="p-4 border-b border-gray-200">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-bold text-gray-800">فك الاعتماد</h3>
          <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm">
            {filteredApprovals.length} اعتماد
          </span>
        </div>

        {/* Filters */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          {/* Class Filter */}
          <div>
            <select
              value={filters.classId}
              onChange={(e) => setFilters(prev => ({ ...prev, classId: e.target.value }))}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">كل الفصول</option>
              {classes.map(cls => (
                <option key={cls._id} value={cls._id}>{cls.name}</option>
              ))}
            </select>
          </div>

          {/* Subject Filter */}
          <div>
            <select
              value={filters.subjectId}
              onChange={(e) => setFilters(prev => ({ ...prev, subjectId: e.target.value }))}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">كل المواد</option>
              {subjects.map(sub => (
                <option key={sub._id} value={sub._id}>{sub.name}</option>
              ))}
            </select>
          </div>

          {/* Period Filter */}
          <div>
            <select
              value={filters.period}
              onChange={(e) => setFilters(prev => ({ ...prev, period: e.target.value }))}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">كل الفترات</option>
              <option value="first">الأولى</option>
              <option value="second">الثانية</option>
            </select>
          </div>

          {/* Level Filter */}
          <div>
            <select
              value={filters.level}
              onChange={(e) => setFilters(prev => ({ ...prev, level: e.target.value }))}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">كل المستويات</option>
              <option value="teacher">اعتماد المعلم</option>
              <option value="head">اعتماد رئيس القسم</option>
            </select>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 text-gray-700">
              <th className="px-4 py-3 text-right font-semibold">الفصل</th>
              <th className="px-4 py-3 text-right font-semibold">المادة</th>
              <th className="px-4 py-3 text-center font-semibold">الفترة</th>
              <th className="px-4 py-3 text-center font-semibold">المستوى</th>
              <th className="px-4 py-3 text-center font-semibold">الأعمال</th>
              <th className="px-4 py-3 text-center font-semibold">الاختبار</th>
              <th className="px-4 py-3 text-center font-semibold">الإجراء</th>
            </tr>
          </thead>
          <tbody>
            {filteredApprovals.map((approval, index) => (
              <tr 
                key={approval._id || index} 
                className={`border-b transition-colors hover:bg-gray-50
                  ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}
                `}
              >
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2">
                    <User className="w-4 h-4 text-gray-400" />
                    <span className="font-medium">{approval.className}</span>
                  </div>
                </td>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2">
                    <BookOpen className="w-4 h-4 text-gray-400" />
                    <span>{approval.subjectName}</span>
                  </div>
                </td>
                <td className="px-4 py-3 text-center">
                  <span className="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">
                    {approval.period === 'first' ? 'الأولى' : 'الثانية'}
                  </span>
                </td>
                <td className="px-4 py-3 text-center">
                  <span className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium border
                    ${getLevelColor(approval.level)}
                  `}>
                    {getStatusIcon(approval.level === 'teacher' ? approval.workTeacherStatus : approval.workHeadStatus)}
                    {getLevelLabel(approval.level)}
                  </span>
                </td>
                <td className="px-4 py-3 text-center">
                  <span className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs
                    ${approval.workApproved ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}
                  `}>
                    {approval.workApproved ? <CheckCircle className="w-3 h-3" /> : <AlertCircle className="w-3 h-3" />}
                    {approval.workApproved ? 'معتمد' : 'غير معتمد'}
                  </span>
                </td>
                <td className="px-4 py-3 text-center">
                  <span className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs
                    ${approval.examApproved ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}
                  `}>
                    {approval.examApproved ? <CheckCircle className="w-3 h-3" /> : <AlertCircle className="w-3 h-3" />}
                    {approval.examApproved ? 'معتمد' : 'غير معتمد'}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <div className="flex items-center justify-center">
                    <button
                      onClick={() => setShowConfirm(approval)}
                      className="flex items-center gap-1 px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors text-xs"
                    >
                      <Unlock className="w-3 h-3" />
                      فك الاعتماد
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Empty State */}
      {filteredApprovals.length === 0 && !loading && (
        <div className="text-center py-12 text-gray-500">
          <AlertCircle className="w-12 h-12 mx-auto mb-3 text-gray-300" />
          <p>لا توجد اعتمادات</p>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="text-center py-12">
          <div className="animate-spin w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full mx-auto mb-4"></div>
          <p className="text-gray-600">جاري التحميل...</p>
        </div>
      )}

      {/* Confirm Unlock Modal */}
      {showConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <div className="flex items-center gap-3 mb-4 text-red-600">
              <Unlock className="w-8 h-8" />
              <h3 className="text-lg font-bold">تأكيد فك الاعتماد</h3>
            </div>

            <div className="mb-4 p-3 bg-gray-50 rounded-lg">
              <p className="text-sm text-gray-700">
                <span className="font-bold">الفصل:</span> {showConfirm.className}
              </p>
              <p className="text-sm text-gray-700">
                <span className="font-bold">المادة:</span> {showConfirm.subjectName}
              </p>
              <p className="text-sm text-gray-700">
                <span className="font-bold">المستوى:</span> {getLevelLabel(showConfirm.level)}
              </p>
            </div>

            <p className="text-gray-600 mb-4">
              اختر نوع الاعتماد المراد فكه:
            </p>

            <div className="flex gap-2 mb-6">
              <button
                onClick={() => setUnlockType('work')}
                className={`flex-1 py-2 px-3 rounded-lg border transition-all
                  ${unlockType === 'work' ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'border-gray-300'}
                `}
              >
                الأعمال فقط
              </button>
              <button
                onClick={() => setUnlockType('exam')}
                className={`flex-1 py-2 px-3 rounded-lg border transition-all
                  ${unlockType === 'exam' ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'border-gray-300'}
                `}
              >
                الاختبار فقط
              </button>
              <button
                onClick={() => setUnlockType('all')}
                className={`flex-1 py-2 px-3 rounded-lg border transition-all
                  ${unlockType === 'all' ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'border-gray-300'}
                `}
              >
                الاثنين
              </button>
            </div>

            <p className="text-red-500 text-sm mb-4">
              بعد فك الاعتماد، يمكن {showConfirm.level === 'teacher' ? 'للمعلم' : 'لرئيس القسم'} تعديل الدرجات مرة أخرى.
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
                  onUnlock({
                    classId: showConfirm.classId,
                    subjectId: showConfirm.subjectId,
                    period: showConfirm.period,
                    type: unlockType,
                    level: showConfirm.level
                  });
                  setShowConfirm(null);
                }}
                className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
              >
                فك الاعتماد
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ApprovalUnlock;
