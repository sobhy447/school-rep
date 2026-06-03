import React, { useState } from 'react';
import { Calendar, Lock, Unlock, AlertCircle, CheckCircle, Save } from 'lucide-react';

const PeriodSettings = ({ settings, onUpdate, loading }) => {
  const [localSettings, setLocalSettings] = useState({
    firstPeriodEnabled: settings?.firstPeriodEnabled ?? true,
    secondPeriodEnabled: settings?.secondPeriodEnabled ?? true
  });
  const [showConfirm, setShowConfirm] = useState(false);

  const handleToggle = (period) => {
    const key = period === 'first' ? 'firstPeriodEnabled' : 'secondPeriodEnabled';
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
    localSettings.firstPeriodEnabled !== settings?.firstPeriodEnabled ||
    localSettings.secondPeriodEnabled !== settings?.secondPeriodEnabled;

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
          <Calendar className="w-6 h-6 text-white" />
        </div>
        <div>
          <h3 className="text-lg font-bold text-gray-800">إعدادات الفترات</h3>
          <p className="text-sm text-gray-500">تحكم في الفترات النشطة للرصد</p>
        </div>
      </div>

      {/* Info */}
      <div className="mb-6 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div className="flex items-start gap-2">
          <AlertCircle className="w-5 h-5 text-yellow-600 mt-0.5" />
          <div className="text-sm text-yellow-800">
            <p className="font-medium mb-1">تنبيه مهم:</p>
            <p>عند إغلاق فترة، لا يمكن للمعلمين رصد درجات في تلك الفترة.</p>
            <p>تأكد من اعتماد جميع الدرجات قبل الإغلاق.</p>
          </div>
        </div>
      </div>

      {/* Period Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {/* First Period */}
        <div className={`p-4 rounded-xl border-2 transition-all
          ${localSettings.firstPeriodEnabled 
            ? 'border-green-300 bg-green-50' 
            : 'border-gray-300 bg-gray-50'
          }
        `}>
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <Calendar className={`w-5 h-5 ${localSettings.firstPeriodEnabled ? 'text-green-600' : 'text-gray-400'}`} />
              <span className={`font-bold ${localSettings.firstPeriodEnabled ? 'text-green-800' : 'text-gray-600'}`}>
                الفترة الأولى
              </span>
            </div>
            <button
              onClick={() => handleToggle('first')}
              className={`relative w-14 h-8 rounded-full transition-colors
                ${localSettings.firstPeriodEnabled ? 'bg-green-500' : 'bg-gray-300'}
              `}
            >
              <span className={`absolute top-1 w-6 h-6 bg-white rounded-full shadow transition-transform
                ${localSettings.firstPeriodEnabled ? 'right-1' : 'left-1'}
              `}>
                {localSettings.firstPeriodEnabled ? (
                  <Unlock className="w-4 h-4 text-green-600 m-0.5" />
                ) : (
                  <Lock className="w-4 h-4 text-gray-500 m-0.5" />
                )}
              </span>
            </button>
          </div>
          <p className={`text-sm ${localSettings.firstPeriodEnabled ? 'text-green-700' : 'text-gray-500'}`}>
            {localSettings.firstPeriodEnabled 
              ? '✅ الفترة مفتوحة - المعلمون يمكنهم رصد الدرجات' 
              : '🔒 الفترة مغلقة - لا يمكن رصد الدرجات'}
          </p>
        </div>

        {/* Second Period */}
        <div className={`p-4 rounded-xl border-2 transition-all
          ${localSettings.secondPeriodEnabled 
            ? 'border-green-300 bg-green-50' 
            : 'border-gray-300 bg-gray-50'
          }
        `}>
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <Calendar className={`w-5 h-5 ${localSettings.secondPeriodEnabled ? 'text-green-600' : 'text-gray-400'}`} />
              <span className={`font-bold ${localSettings.secondPeriodEnabled ? 'text-green-800' : 'text-gray-600'}`}>
                الفترة الثانية
              </span>
            </div>
            <button
              onClick={() => handleToggle('second')}
              className={`relative w-14 h-8 rounded-full transition-colors
                ${localSettings.secondPeriodEnabled ? 'bg-green-500' : 'bg-gray-300'}
              `}
            >
              <span className={`absolute top-1 w-6 h-6 bg-white rounded-full shadow transition-transform
                ${localSettings.secondPeriodEnabled ? 'right-1' : 'left-1'}
              `}>
                {localSettings.secondPeriodEnabled ? (
                  <Unlock className="w-4 h-4 text-green-600 m-0.5" />
                ) : (
                  <Lock className="w-4 h-4 text-gray-500 m-0.5" />
                )}
              </span>
            </button>
          </div>
          <p className={`text-sm ${localSettings.secondPeriodEnabled ? 'text-green-700' : 'text-gray-500'}`}>
            {localSettings.secondPeriodEnabled 
              ? '✅ الفترة مفتوحة - المعلمون يمكنهم رصد الدرجات' 
              : '🔒 الفترة مغلقة - لا يمكن رصد الدرجات'}
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
              {localSettings.firstPeriodEnabled !== settings?.firstPeriodEnabled && (
                <p className="text-sm text-gray-700">
                  الفترة الأولى: {localSettings.firstPeriodEnabled ? 'فتح' : 'إغلاق'}
                </p>
              )}
              {localSettings.secondPeriodEnabled !== settings?.secondPeriodEnabled && (
                <p className="text-sm text-gray-700">
                  الفترة الثانية: {localSettings.secondPeriodEnabled ? 'فتح' : 'إغلاق'}
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

export default PeriodSettings;
