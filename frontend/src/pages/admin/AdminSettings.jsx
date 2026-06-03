import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowRight, Settings, AlertCircle, CheckCircle } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useAdminSettings } from '../../hooks/useAdminSettings';
import ApprovalUnlock from '../../components/admin/ApprovalUnlock';
import PeriodSettings from '../../components/admin/PeriodSettings';
import ColumnSettings from '../../components/admin/ColumnSettings';

const AdminSettings = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const {
    loading,
    error,
    settings,
    approvals,
    classes,
    subjects,
    loadSettings,
    loadApprovals,
    loadClassesAndSubjects,
    updateSettings,
    unlockApproval
  } = useAdminSettings();

  const [activeTab, setActiveTab] = useState('period'); // period, column, unlock
  const [notification, setNotification] = useState(null);

  useEffect(() => {
    if (!isAuthenticated) navigate('/login');
  }, [isAuthenticated, navigate]);

  useEffect(() => {
    if (isAuthenticated) {
      loadSettings();
      loadApprovals();
      loadClassesAndSubjects();
    }
  }, [isAuthenticated, loadSettings, loadApprovals, loadClassesAndSubjects]);

  const showNotification = (message, type = 'success') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  const handleUpdateSettings = async (newSettings) => {
    const success = await updateSettings(newSettings);
    if (success) {
      showNotification('تم حفظ الإعدادات بنجاح', 'success');
    }
    return success;
  };

  const handleUnlock = async (data) => {
    const success = await unlockApproval(data);
    if (success) {
      showNotification('تم فك الاعتماد بنجاح', 'success');
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
                <Settings className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-gray-800">إعدادات النظام</h1>
                <p className="text-sm text-gray-500">تحكم في الفترات والأعمدة والاعتمادات</p>
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

        {/* Tabs */}
        <div className="bg-white rounded-xl border border-gray-200 p-1 shadow-sm">
          <div className="flex">
            <button
              onClick={() => setActiveTab('period')}
              className={`flex-1 py-3 px-4 rounded-lg font-medium transition-all
                ${activeTab === 'period' 
                  ? 'bg-blue-100 text-blue-700' 
                  : 'text-gray-600 hover:bg-gray-50'
                }
              `}
            >
              الفترات الدراسية
            </button>
            <button
              onClick={() => setActiveTab('column')}
              className={`flex-1 py-3 px-4 rounded-lg font-medium transition-all
                ${activeTab === 'column' 
                  ? 'bg-purple-100 text-purple-700' 
                  : 'text-gray-600 hover:bg-gray-50'
                }
              `}
            >
              الأعمدة المرصودة
            </button>
            <button
              onClick={() => setActiveTab('unlock')}
              className={`flex-1 py-3 px-4 rounded-lg font-medium transition-all
                ${activeTab === 'unlock' 
                  ? 'bg-red-100 text-red-700' 
                  : 'text-gray-600 hover:bg-gray-50'
                }
              `}
            >
              فك الاعتماد
            </button>
          </div>
        </div>

        {/* Content */}
        {activeTab === 'period' && (
          <PeriodSettings
            settings={settings}
            onUpdate={handleUpdateSettings}
            loading={loading}
          />
        )}

        {activeTab === 'column' && (
          <ColumnSettings
            settings={settings}
            onUpdate={handleUpdateSettings}
            loading={loading}
          />
        )}

        {activeTab === 'unlock' && (
          <ApprovalUnlock
            approvals={approvals}
            classes={classes}
            subjects={subjects}
            onUnlock={handleUnlock}
            loading={loading}
          />
        )}
      </div>
    </div>
  );
};

export default AdminSettings;
