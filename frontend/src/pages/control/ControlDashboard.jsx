import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Printer, Eye, Filter, ArrowRight, AlertCircle, CheckCircle, FileText } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useControl } from '../../hooks/useControl';
import ControlReview from '../../components/grades/ControlReview';
import ControlPrintView from '../../components/grades/ControlPrintView';

const GRADE_NAMES = { 6: 'السادس', 7: 'السابع', 8: 'الثامن', 9: 'التاسع' };
const GRADE_COLORS = {
  6: 'bg-yellow-500',
  7: 'bg-green-500',
  8: 'bg-red-500',
  9: 'bg-blue-500'
};

const ControlDashboard = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const {
    loading,
    error,
    allGrades,
    selectedGrade,
    printData,
    filters,
    setSelectedGrade,
    loadAllGrades,
    loadPrintData,
    updateFilter,
    getGradeLevels,
    getSubjects,
    getFilteredGrades,
    isFullyApproved
  } = useControl();

  const [reviewMode, setReviewMode] = useState(false);
  const [printMode, setPrintMode] = useState(false);
  const [notification, setNotification] = useState(null);

  useEffect(() => {
    if (!isAuthenticated) navigate('/login');
  }, [isAuthenticated, navigate]);

  useEffect(() => {
    if (isAuthenticated) loadAllGrades();
  }, [isAuthenticated, loadAllGrades]);

  const showNotification = (message, type = 'success') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  const handleReview = (grade) => {
    setSelectedGrade(grade);
    setReviewMode(true);
  };

  const handlePrint = async (grade) => {
    await loadPrintData(grade.classId, grade.subjectId, grade.period, 'all');
    setPrintMode(true);
  };

  const handleBack = () => {
    setReviewMode(false);
    setPrintMode(false);
    setSelectedGrade(null);
  };

  const filteredGrades = getFilteredGrades();
  const gradeLevels = getGradeLevels();
  const subjects = getSubjects();

  return (
    <div className="min-h-screen bg-gray-50" dir="rtl">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                <FileText className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-gray-800">لوحة الكنترول</h1>
                <p className="text-sm text-gray-500">مراجعة وطباعة كشوف الرصد</p>
              </div>
            </div>
            <button
              onClick={() => navigate('/dashboard')}
              className="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition-colors"
            >
              <ArrowRight className="w-5 h-5" />
              العودة للرئيسية
            </button>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-6">
        {/* Notification */}
        {notification && (
          <div className={`mb-4 p-4 rounded-lg flex items-center gap-3 animate-fade-in
            ${notification.type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}
          `}>
            {notification.type === 'success' ? <CheckCircle className="w-5 h-5" /> : <AlertCircle className="w-5 h-5" />}
            {notification.message}
          </div>
        )}

        {/* Error */}
        {error && (
          <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3 text-red-700">
            <AlertCircle className="w-5 h-5" />
            {error}
          </div>
        )}

        {/* Review Mode */}
        {reviewMode && selectedGrade ? (
          <ControlReview
            grade={selectedGrade}
            onBack={handleBack}
            onPrint={() => handlePrint(selectedGrade)}
          />
        ) : printMode && printData ? (
          <ControlPrintView
            printData={printData}
            onBack={handleBack}
          />
        ) : (
          <>
            {/* Filters */}
            <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm mb-6">
              <div className="flex items-center gap-2 mb-4">
                <Filter className="w-5 h-5 text-gray-600" />
                <h3 className="font-bold text-gray-800">تصفية النتائج</h3>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                {/* Grade Level */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">المرحلة</label>
                  <select
                    value={filters.gradeLevel}
                    onChange={(e) => updateFilter('gradeLevel', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="">الكل</option>
                    {gradeLevels.map(level => (
                      <option key={level} value={level}>
                        {GRADE_NAMES[level] || `المرحلة ${level}`}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Subject */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">المادة</label>
                  <select
                    value={filters.subject}
                    onChange={(e) => updateFilter('subject', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="">الكل</option>
                    {subjects.map(subject => (
                      <option key={subject} value={subject}>{subject}</option>
                    ))}
                  </select>
                </div>

                {/* Period */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">الفترة</label>
                  <select
                    value={filters.period}
                    onChange={(e) => updateFilter('period', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="first">الفترة الأولى</option>
                    <option value="second">الفترة الثانية</option>
                  </select>
                </div>

                {/* Type */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">النوع</label>
                  <select
                    value={filters.type}
                    onChange={(e) => updateFilter('type', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="all">الكل</option>
                    <option value="work">الأعمال فقط</option>
                    <option value="exam">الاختبار فقط</option>
                  </select>
                </div>
              </div>
            </div>

            {/* Stats Summary */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
              <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <div className="text-2xl font-bold text-indigo-600">{allGrades.length}</div>
                <div className="text-sm text-gray-500">إجمالي الفصول</div>
              </div>
              <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <div className="text-2xl font-bold text-green-600">
                  {allGrades.filter(g => isFullyApproved(g)).length}
                </div>
                <div className="text-sm text-gray-500">معتمدة بالكامل</div>
              </div>
              <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <div className="text-2xl font-bold text-yellow-600">
                  {allGrades.filter(g => !isFullyApproved(g)).length}
                </div>
                <div className="text-sm text-gray-500">قيد الانتظار</div>
              </div>
              <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <div className="text-2xl font-bold text-blue-600">
                  {new Set(allGrades.map(g => g.subjectName)).size}
                </div>
                <div className="text-sm text-gray-500">عدد المواد</div>
              </div>
            </div>

            {/* Grades Table */}
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
              <div className="p-4 border-b border-gray-200">
                <h2 className="text-lg font-bold text-gray-800">كشوف الرصد المعتمدة</h2>
                <p className="text-sm text-gray-500 mt-1">
                  يظهر فقط الفصول التي تم اعتمادها من رؤساء الأقسام
                </p>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-gray-50 text-gray-700">
                      <th className="px-4 py-3 text-right font-semibold">المرحلة</th>
                      <th className="px-4 py-3 text-right font-semibold">الفصل</th>
                      <th className="px-4 py-3 text-right font-semibold">المادة</th>
                      <th className="px-4 py-3 text-right font-semibold">المعلم</th>
                      <th className="px-4 py-3 text-center font-semibold">حالة الأعمال</th>
                      <th className="px-4 py-3 text-center font-semibold">حالة الاختبار</th>
                      <th className="px-4 py-3 text-center font-semibold">الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredGrades.map((grade, index) => {
                      const fullyApproved = isFullyApproved(grade);

                      return (
                        <tr 
                          key={grade._id || index} 
                          className={`border-b transition-colors hover:bg-gray-50
                            ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}
                            ${fullyApproved ? '' : 'opacity-60'}
                          `}
                        >
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-2">
                              <span className={`w-3 h-3 rounded-full ${GRADE_COLORS[grade.gradeLevel] || 'bg-gray-400'}`}></span>
                              <span className="font-medium">{GRADE_NAMES[grade.gradeLevel] || grade.gradeLevel}</span>
                            </div>
                          </td>
                          <td className="px-4 py-3 font-medium">{grade.className}</td>
                          <td className="px-4 py-3">{grade.subjectName}</td>
                          <td className="px-4 py-3">{grade.teacherName}</td>
                          <td className="px-4 py-3 text-center">
                            <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium
                              ${grade.workDeptApproved 
                                ? 'bg-green-100 text-green-700' 
                                : 'bg-yellow-100 text-yellow-700'
                              }
                            `}>
                              {grade.workDeptApproved ? (
                                <><CheckCircle className="w-3 h-3" /> معتمد</>
                              ) : (
                                <><AlertCircle className="w-3 h-3" /> قيد الانتظار</>
                              )}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-center">
                            <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium
                              ${grade.examDeptApproved 
                                ? 'bg-green-100 text-green-700' 
                                : 'bg-yellow-100 text-yellow-700'
                              }
                            `}>
                              {grade.examDeptApproved ? (
                                <><CheckCircle className="w-3 h-3" /> معتمد</>
                              ) : (
                                <><AlertCircle className="w-3 h-3" /> قيد الانتظار</>
                              )}
                            </span>
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex items-center justify-center gap-2">
                              <button
                                onClick={() => handleReview(grade)}
                                className="flex items-center gap-1 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-xs"
                              >
                                <Eye className="w-3 h-3" />
                                عرض
                              </button>
                              {fullyApproved && (
                                <button
                                  onClick={() => handlePrint(grade)}
                                  className="flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-xs"
                                >
                                  <Printer className="w-3 h-3" />
                                  طباعة
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {filteredGrades.length === 0 && !loading && (
                <div className="text-center py-12 text-gray-500">
                  <FileText className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                  <p>لا توجد كشوف معتمدة</p>
                  <p className="text-sm mt-1">تأكد من اعتماد رؤساء الأقسام للدرجات</p>
                </div>
              )}
            </div>

            {/* Loading */}
            {loading && (
              <div className="mt-6 bg-white rounded-xl border border-gray-200 p-12 text-center shadow-sm">
                <div className="animate-spin w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full mx-auto mb-4"></div>
                <p className="text-gray-600">جاري التحميل...</p>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
};

export default ControlDashboard;
