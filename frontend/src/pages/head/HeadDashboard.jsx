import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Users, GraduationCap, ArrowRight, BookOpen, AlertCircle, CheckCircle } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useHeadGrades } from '../../hooks/useHeadGrades';
import { useGrades } from '../../hooks/useGrades';
import HeadClassView from '../../components/grades/HeadClassView';
import HeadGradeReview from '../../components/grades/HeadGradeReview';
import DepartmentApproval from '../../components/grades/DepartmentApproval';
import PeriodSelector from '../../components/grades/PeriodSelector';

const HeadDashboard = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const {
    loading,
    error,
    allClasses,
    headClasses,
    reviewData,
    selectedClass,
    selectedTeacher,
    selectedSubject,
    deptApprovalStatus,
    canApproveDept,
    loadAllClasses,
    loadHeadClasses,
    reviewTeacherGrades,
    editGradeAsHead,
    approveDepartment,
    setSelectedClass,
    setSelectedTeacher,
    setSelectedSubject
  } = useHeadGrades();

  const {
    period,
    setPeriod,
    settings
  } = useGrades();

  const [activeTab, setActiveTab] = useState('review'); // 'review' or 'my-classes'
  const [notification, setNotification] = useState(null);
  const [reviewMode, setReviewMode] = useState(false);

  // Check authentication
  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login');
    }
  }, [isAuthenticated, navigate]);

  // Load data on mount
  useEffect(() => {
    if (isAuthenticated) {
      loadAllClasses();
      loadHeadClasses();
    }
  }, [isAuthenticated, loadAllClasses, loadHeadClasses]);

  // Show notification
  const showNotification = (message, type = 'success') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  // Handle review
  const handleReview = async (classId, teacherId, subjectId) => {
    await reviewTeacherGrades(classId, teacherId, subjectId, period);
    setReviewMode(true);
  };

  // Handle enter grades (head's own classes)
  const handleEnterGrades = (classId, subjectId) => {
    navigate(`/grades/entry?class=${classId}&subject=${subjectId}`);
  };

  // Handle department approval
  const handleApproveDept = async (type) => {
    const success = await approveDepartment(type, period);
    if (success) {
      showNotification(
        type === 'work' 
          ? 'تم اعتماد الأعمال للقسم بنجاح - سيتم إشعار المدير'
          : 'تم اعتماد الاختبار للقسم بنجاح - سيتم إشعار المدير',
        'success'
      );
    }
  };

  // Handle back from review
  const handleBackFromReview = () => {
    setReviewMode(false);
    setSelectedClass(null);
    setSelectedTeacher(null);
    setSelectedSubject(null);
  };

  return (
    <div className="min-h-screen bg-gray-50" dir="rtl">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
                <Users className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-gray-800">لوحة رئيس القسم</h1>
                <p className="text-sm text-gray-500">
                  {user?.name} | {user?.subject || 'قسم المادة'}
                </p>
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

        {/* Review Mode */}
        {reviewMode && reviewData ? (
          <div className="space-y-6">
            <HeadGradeReview
              reviewData={reviewData}
              onEditGrade={editGradeAsHead}
              onSave={() => showNotification('تم حفظ التعديلات', 'success')}
              onBack={handleBackFromReview}
              loading={loading}
            />
          </div>
        ) : (
          <>
            {/* Tabs */}
            <div className="mb-6 bg-white rounded-xl border border-gray-200 p-1 shadow-sm">
              <div className="flex">
                <button
                  onClick={() => setActiveTab('review')}
                  className={`flex-1 py-3 px-4 rounded-lg font-medium transition-all
                    ${activeTab === 'review' 
                      ? 'bg-purple-100 text-purple-700' 
                      : 'text-gray-600 hover:bg-gray-50'
                    }
                  `}
                >
                  <div className="flex items-center justify-center gap-2">
                    <Users className="w-5 h-5" />
                    مراجعة فصول القسم
                    <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
                      {allClasses.length}
                    </span>
                  </div>
                </button>
                <button
                  onClick={() => setActiveTab('my-classes')}
                  className={`flex-1 py-3 px-4 rounded-lg font-medium transition-all
                    ${activeTab === 'my-classes' 
                      ? 'bg-blue-100 text-blue-700' 
                      : 'text-gray-600 hover:bg-gray-50'
                    }
                  `}
                >
                  <div className="flex items-center justify-center gap-2">
                    <GraduationCap className="w-5 h-5" />
                    فصولي (كمعلم)
                    <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
                      {headClasses.length}
                    </span>
                  </div>
                </button>
              </div>
            </div>

            {/* Department Approval (only in review tab) */}
            {activeTab === 'review' && (
              <div className="mb-6">
                <DepartmentApproval
                  classes={allClasses}
                  canApprove={canApproveDept}
                  onApproveWork={() => handleApproveDept('work')}
                  onApproveExam={() => handleApproveDept('exam')}
                  approvalStatus={deptApprovalStatus}
                  loading={loading}
                />
              </div>
            )}

            {/* Content */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
              {activeTab === 'review' ? (
                <>
                  <h2 className="text-lg font-bold text-gray-800 mb-4">
                    فصول قسم {user?.subject || 'المادة'}
                  </h2>
                  <p className="text-sm text-gray-500 mb-6">
                    يظهر فقط معلمي قسمك. الفصول المعتمدة تظهر بعلامة ✅، والفصول غير المعتمدة تظهر بعلامة ⚠️
                  </p>
                  <HeadClassView
                    classes={allClasses}
                    onReview={handleReview}
                    onEnterGrades={handleEnterGrades}
                    period={period}
                  />
                </>
              ) : (
                <>
                  <h2 className="text-lg font-bold text-gray-800 mb-4">
                    فصولي (كمعلم)
                  </h2>
                  <p className="text-sm text-gray-500 mb-6">
                    فصولك الخاصة التي تدرس فيها. اضغط "رصد درجاتي" للدخول لنظام الرصد.
                  </p>
                  <HeadClassView
                    classes={headClasses}
                    onReview={handleReview}
                    onEnterGrades={handleEnterGrades}
                    period={period}
                  />
                </>
              )}
            </div>
          </>
        )}

        {/* Loading */}
        {loading && !reviewMode && (
          <div className="mt-6 bg-white rounded-xl border border-gray-200 p-12 text-center shadow-sm">
            <div className="animate-spin w-8 h-8 border-4 border-purple-600 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p className="text-gray-600">جاري التحميل...</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default HeadDashboard;
