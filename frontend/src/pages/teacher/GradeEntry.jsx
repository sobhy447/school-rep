import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { GraduationCap, ArrowRight, AlertCircle, CheckCircle } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useGrades } from '../../hooks/useGrades';
import ClassSelector from '../../components/grades/ClassSelector';
import PeriodSelector from '../../components/grades/PeriodSelector';
import GradeEntryTable from '../../components/grades/GradeEntryTable';

const TeacherGradeEntry = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const {
    loading,
    error,
    classes,
    students,
    grades,
    excuses,
    approvalStatus,
    period,
    settings,
    selectedClass,
    selectedSubject,
    setSelectedClass,
    setSelectedSubject,
    setPeriod,
    loadClasses,
    loadGrades,
    updateGrade,
    saveGrades,
    submitGrades,
    hasExcuse,
    canEdit
  } = useGrades();

  const [notification, setNotification] = useState(null);
  const [showSuccess, setShowSuccess] = useState(false);

  // Check authentication
  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login');
    }
  }, [isAuthenticated, navigate]);

  // Load classes on mount
  useEffect(() => {
    if (isAuthenticated) {
      loadClasses();
    }
  }, [isAuthenticated, loadClasses]);

  // Load grades when class/subject/period changes
  useEffect(() => {
    if (selectedClass && selectedSubject && period) {
      loadGrades(selectedClass, selectedSubject, period);
    }
  }, [selectedClass, selectedSubject, period, loadGrades]);

  // Show success notification
  const showNotification = (message, type = 'success') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  // Handle save
  const handleSave = async () => {
    const success = await saveGrades();
    if (success) {
      showNotification('تم حفظ الدرجات بنجاح', 'success');
    }
  };

  // Handle submit
  const handleSubmit = async (type) => {
    const success = await submitGrades(type);
    if (success) {
      showNotification(
        type === 'work' 
          ? 'تم اعتماد درجات الأعمال بنجاح - سيتم إشعار رئيس القسم'
          : 'تم اعتماد درجات الاختبار بنجاح - سيتم إشعار رئيس القسم',
        'success'
      );
    }
  };

  // Get selected class info
  const selectedClassInfo = classes.find(c => c._id === selectedClass);
  const selectedSubjectInfo = selectedClassInfo?.subjects?.find(s => s._id === selectedSubject);

  return (
    <div className="min-h-screen bg-gray-50" dir="rtl">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <GraduationCap className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-gray-800">رصد الدرجات</h1>
                <p className="text-sm text-gray-500">المعلم: {user?.name}</p>
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

        {/* Period Selector */}
        <div className="mb-6">
          <PeriodSelector
            period={period}
            onChange={setPeriod}
            settings={{
              firstPeriodEnabled: settings.firstPeriodEnabled !== false,
              secondPeriodEnabled: settings.secondPeriodEnabled !== false
            }}
            disabled={loading}
          />
        </div>

        {/* Class Selection */}
        {!selectedClass && (
          <div className="mb-6">
            <h2 className="text-lg font-bold text-gray-800 mb-4">اختر الفصل</h2>
            <ClassSelector
              classes={classes}
              selectedClass={selectedClass}
              selectedSubject={selectedSubject}
              onSelectClass={(classId) => {
                setSelectedClass(classId);
                const cls = classes.find(c => c._id === classId);
                if (cls?.subjects?.length === 1) {
                  setSelectedSubject(cls.subjects[0]._id);
                }
              }}
              onSelectSubject={setSelectedSubject}
            />
          </div>
        )}

        {/* Selected Class Info */}
        {selectedClass && selectedSubject && (
          <div className="mb-6">
            <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <button
                    onClick={() => {
                      setSelectedClass(null);
                      setSelectedSubject(null);
                    }}
                    className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                  >
                    تغيير الفصل
                  </button>
                  <div className="h-6 w-px bg-gray-300"></div>
                  <div>
                    <span className="text-gray-500">الفصل:</span>
                    <span className="font-bold text-gray-800 mr-1">{selectedClassInfo?.name}</span>
                  </div>
                  <div className="h-6 w-px bg-gray-300"></div>
                  <div>
                    <span className="text-gray-500">المادة:</span>
                    <span className="font-bold text-gray-800 mr-1">{selectedSubjectInfo?.name || 'غير محدد'}</span>
                  </div>
                  <div className="h-6 w-px bg-gray-300"></div>
                  <div>
                    <span className="text-gray-500">الفترة:</span>
                    <span className="font-bold text-gray-800 mr-1">
                      {period === 'first' ? 'الأولى' : 'الثانية'}
                    </span>
                  </div>
                </div>
                <div className="text-sm text-gray-500">
                  {students.length} طالب
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Grade Entry Table */}
        {selectedClass && selectedSubject && students.length > 0 && (
          <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <GradeEntryTable
              students={students}
              grades={grades}
              excuses={excuses}
              approvalStatus={approvalStatus}
              settings={settings}
              onUpdateGrade={updateGrade}
              onSave={handleSave}
              onSubmit={handleSubmit}
              canEdit={canEdit}
              hasExcuse={hasExcuse}
              loading={loading}
            />
          </div>
        )}

        {/* Empty State */}
        {selectedClass && selectedSubject && students.length === 0 && !loading && (
          <div className="bg-white rounded-xl border border-gray-200 p-12 text-center shadow-sm">
            <GraduationCap className="w-16 h-16 mx-auto text-gray-300 mb-4" />
            <h3 className="text-lg font-bold text-gray-600 mb-2">لا يوجد طلاب</h3>
            <p className="text-gray-500">هذا الفصل لا يحتوي على طلاب مسجلين</p>
          </div>
        )}

        {/* Loading */}
        {loading && (
          <div className="bg-white rounded-xl border border-gray-200 p-12 text-center shadow-sm">
            <div className="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p className="text-gray-600">جاري التحميل...</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default TeacherGradeEntry;
