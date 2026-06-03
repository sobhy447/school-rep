import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowRight, AlertCircle, CheckCircle, Shield } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useExcuses } from '../../hooks/useExcuses';
import ExcuseForm from '../../components/excuses/ExcuseForm';
import ExcuseList from '../../components/excuses/ExcuseList';

const ExcuseManager = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const {
    loading,
    error,
    excuses,
    students,
    classes,
    subjects,
    loadExcuses,
    loadStudents,
    loadClasses,
    loadSubjects,
    addExcuse,
    deleteExcuse
  } = useExcuses();

  const [notification, setNotification] = useState(null);

  useEffect(() => {
    if (!isAuthenticated) navigate('/login');
  }, [isAuthenticated, navigate]);

  useEffect(() => {
    if (isAuthenticated) {
      loadExcuses();
      loadClasses();
      loadSubjects();
    }
  }, [isAuthenticated, loadExcuses, loadClasses, loadSubjects]);

  const showNotification = (message, type = 'success') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  const handleAddExcuse = async (formData) => {
    const success = await addExcuse(formData);
    if (success) {
      showNotification('تم إضافة العذر بنجاح', 'success');
    }
    return success;
  };

  const handleDeleteExcuse = async (excuseId) => {
    const success = await deleteExcuse(excuseId);
    if (success) {
      showNotification('تم حذف العذر بنجاح', 'success');
    }
  };

  return (
    <div className="min-h-screen bg-gray-50" dir="rtl">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                <Shield className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-gray-800">إدارة الاعذار</h1>
                <p className="text-sm text-gray-500">إضافة وعرض اعذار الطلاب</p>
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

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Notification */}
        {notification && (
          <div className={`p-4 rounded-lg flex items-center gap-3 animate-fade-in
            ${notification.type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}
          `}>
            {notification.type === 'success' ? <CheckCircle className="w-5 h-5" /> : <AlertCircle className="w-5 h-5" />}
            {notification.message}
          </div>
        )}

        {/* Error */}
        {error && (
          <div className="p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3 text-red-700">
            <AlertCircle className="w-5 h-5" />
            {error}
          </div>
        )}

        {/* Info Card */}
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <div className="flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5" />
            <div className="text-sm text-blue-800">
              <p className="font-medium mb-1">تعليمات:</p>
              <ul className="list-disc list-inside space-y-1 text-blue-700">
                <li>الطالب المعذور لا يمكن رصد درجة له في المادة والنوع المحدد</li>
                <li>الخانة تظهر مقفولة (🔒) في شاشة رصد الدرجات</li>
                <li>يمكن حذف العذر لاحقاً إذا لزم الأمر</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Excuse Form */}
        <ExcuseForm
          classes={classes}
          subjects={subjects}
          students={students}
          onLoadStudents={loadStudents}
          onSubmit={handleAddExcuse}
          loading={loading}
        />

        {/* Excuse List */}
        <ExcuseList
          excuses={excuses}
          onDelete={handleDeleteExcuse}
          loading={loading}
        />
      </div>
    </div>
  );
};

export default ExcuseManager;
