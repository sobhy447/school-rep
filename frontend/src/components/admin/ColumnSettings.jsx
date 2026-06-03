import React, { useState } from 'react';
import { Eye, EyeOff, BookOpen, GraduationCap, AlertCircle, Save } from 'lucide-react';

const ColumnSettings = ({ settings, onUpdate, loading }) => {
  const [localSettings, setLocalSettings] = useState({
    workEnabled: settings?.workEnabled ?? true,
    examEnabled: settings?.examEnabled ?? true
  });
  const [showConfirm, setShowConfirm] = useState(false);

  const handleToggle = (column) => {
    const key = column === 'work' ? 'workEnabled' : 'examEnabled';
    setLocalSettings(prev => ({
      ...prev,
      [key]: !prev[key]
    }));
  };

  const handleSave = () => {
    setShowConfirm(true);
  };

  const confirmSave = async () => {
    const success = await onUpdate(localSettings);
    if (success) {
      setShowConfirm(false);
    }
  };

  const hasChanges = 
    localSettings.workEnabled !== settings?.workEnabled ||
    localSettings.examEnabled !== settings?.examEnabled;

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
          <Eye className="w-6 h-6 text-white" />
        </div>
        <div>
          <h3 className="text-lg font-bold text-gray-800">إعدادات الأعمدة</h3>
          <p className="text-sm text-gray-500">تحكم في الأعمدة المرصودة للمعلمين</p>
        </div>
      </div>

      {/* Info */}
      <div className="mb-6 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div className="flex items-start gap-2">
          <AlertCircle className="w-5 h-5 text-yellow-600 mt-0.5" />
          <div className="text-sm text-yellow-800">
            <p className="font-medium mb-1">تنبيه:</p>
            <p>عند إخفاء عمود، لا يظهر للمعلمين في شاشة الرصد.</p>
            <p>يمكن استخدام هذا لمرحلة رصد الأعمال فقط أو الاختبار فقط.</p>
          </div>
        </div>
      </div>

      {/* Column Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {/* Work Column */}
        <div className={`p-4 rounded-xl border-2 transition-all
          ${localSettings.workEnabled 
            ? 'border-blue-300 bg-blue-50' 
            : 'border-gray-300 bg-gray-50'
          }
        `}>
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <BookOpen className={`w-5 h-5 ${localSettings.workEnabled ? 'text-blue-600' : 'text-gray-400'}`} />
              <span className={`font-bold ${localSettings.workEnabled ? 'text-blue-800' : 'text-gray-600'}`}>
                الأعمال (40)
              </span>
            </div>
            <button
              onClick={() => handleToggle('work')}
              className={`p-2 rounded-lg transition-colors
                ${localSettings.workEnabled 
                  ? 'bg-blue-100 text-blue-700 hover:bg-blue-200' 
                  : 'bg-gray-200 text-gray-500 hover:bg-gray-300'
                }
              `}
            >
              {localSettings.workEnabled ? <Eye className="w-5 h-5" /> : <EyeOff className="w-5 h-5" />}
            </button>
          </div>
          <p className={`text-sm ${localSettings.workEnabled ? 'text-blue-700' : 'text-gray-500'}`}>
            {localSettings.workEnabled 
              ? '✅ العمود ظاهر - المعلمون يمكنهم رصد درجات الأعمال' 
              : '🔒 العمود مخفي - لا يمكن رصد درجات الأعمال'}
          </p>
        </div>

        {/* Exam Column */}
        <div className={`p-4 rounded-xl border-2 transition-all
          ${localSettings.examEnabled 
            ? 'border-purple-300 bg-purple-50' 
            : 'border-gray-300 bg-gray-50'
          }
        `}>
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <GraduationCap className={`w-5 h-5 ${localSettings.examEnabled ? 'text-purple-600' : 'text-gray-400'}`} />
              <span className={`font-bold ${localSettings.examEnabled ? 'text-purple-800' : 'text-gray-600'}`}>
                الاختبار (60)
              </span>
            </div>
            <button
              onClick={() => handleToggle('exam')}
              className={`p-2 rounded-lg transition-colors
                ${localSettings.examEnabled 
                  ? 'bg-purple-100 text-purple-700 hover:bg-purple-200' 
                  : 'bg-gray-200 text-gray-500 hover:bg-gray-300'
                }
              `}
            >
              {localSettings.examEnabled ? <Eye className="w-5 h-5" /> : <EyeOff className="w-5 h-5" />}
            </button>
          </div>
          <p className={`text-sm ${localSettings.examEnabled ? 'text-purple-700' : 'text-gray-500'}`}>
            {localSettings.examEnabled 
              ? '✅ العمود ظاهر - المعلمون يمكنهم رصد درجات الاختبار' 
              : '🔒 العمود مخفي - لا يمكن رصد درجات الاختبار'}
          </p>
        </div>
      </div>

      {/* Save Button */}
      {hasChanges && (
        <div className="flex justify-end">
          <button
            onClick={handleSave}
            disabled={loading}
            className="flex items-center gap-2 px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
          >
            {loading ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                جاري الحفظ...
              </>
            ) : (
              <>
                <Save className="w-4 h-4" />
                حفظ التغييرات
              </>
            )}
          </button>
        </div>
      )}

      {/* Confirm Modal */}
      {showConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <div className="flex items-center gap-3 mb-4 text-amber-600">
              <AlertCircle className="w-8 h-8" />
              <h3 className="text-lg font-bold">تأكيد التغييرات</h3>
            </div>

            <div className="space-y-2 mb-4">
              {localSettings.workEnabled !== settings?.workEnabled && (
                <p className="text-sm text-gray-700">
                  عمود الأعمال: {localSettings.workEnabled ? 'إظهار' : 'إخفاء'}
                </p>
              )}
              {localSettings.examEnabled !== settings?.examEnabled && (
                <p className="text-sm text-gray-700">
                  عمود الاختبار: {localSettings.examEnabled ? 'إظهار' : 'إخفاء'}
                </p>
              )}
            </div>

            <p className="text-red-500 text-sm mb-4">
              هذا التغيير سيؤثر على جميع المعلمين في النظام.
            </p>

            <div className="flex gap-3">
              <button
                onClick={() => setShowConfirm(false)}
                className="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              >
                إلغاء
              </button>
              <button
                onClick={confirmSave}
                className="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
              >
                تأكيد الحفظ
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ColumnSettings;
