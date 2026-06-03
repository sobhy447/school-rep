import React from 'react';
import { Calendar, Lock } from 'lucide-react';

const PeriodSelector = ({ period, onChange, settings, disabled }) => {
  const periods = [
    { id: 'first', name: 'الفترة الأولى', enabled: settings.firstPeriodEnabled },
    { id: 'second', name: 'الفترة الثانية', enabled: settings.secondPeriodEnabled }
  ];

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
      <div className="flex items-center gap-2 mb-3">
        <Calendar className="w-5 h-5 text-gray-600" />
        <h3 className="font-bold text-gray-800">الفترة الدراسية</h3>
      </div>

      <div className="flex gap-3">
        {periods.map(p => (
          <button
            key={p.id}
            onClick={() => p.enabled && !disabled && onChange(p.id)}
            disabled={!p.enabled || disabled}
            className={`flex-1 py-3 px-4 rounded-lg border-2 transition-all duration-200
              ${period === p.id && p.enabled
                ? 'border-blue-500 bg-blue-50 text-blue-700 font-bold shadow-sm'
                : p.enabled
                  ? 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                  : 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'
              }
            `}
          >
            <div className="flex items-center justify-center gap-2">
              {!p.enabled && <Lock className="w-4 h-4" />}
              <span>{p.name}</span>
            </div>
            {!p.enabled && (
              <div className="text-xs mt-1 text-gray-400">مغلقة</div>
            )}
          </button>
        ))}
      </div>
    </div>
  );
};

export default PeriodSelector;
