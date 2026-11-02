import type { FareEstimate, RideCategory } from '../api/types';
import { formatDistance, formatDuration, formatMoney } from '../lib/format';
import { EmptyState, ErrorAlert, Skeleton } from './ui/primitives';

interface RideOptionsProps {
  estimate: FareEstimate | undefined;
  isLoading: boolean;
  error: unknown;
  onRetry: () => void;
  hasRoute: boolean;
  selected: RideCategory;
  onSelect: (category: RideCategory) => void;
  disabled?: boolean;
}

export function RideOptions({ estimate, isLoading, error, onRetry, hasRoute, selected, onSelect, disabled }: RideOptionsProps) {
  if (!hasRoute) {
    return <EmptyState title="Where to?">Choose a pickup and destination to see fares and pickup times.</EmptyState>;
  }

  if (error && !estimate) {
    return <ErrorAlert title="Couldn’t load fares" error={error} onRetry={onRetry} />;
  }

  if (isLoading || !estimate) {
    return (
      <div className="space-y-3" aria-label="Loading fares" role="status">
        <Skeleton className="h-20" />
        <Skeleton className="h-20" />
      </div>
    );
  }

  return (
    <fieldset disabled={disabled}>
      <legend className="flex w-full justify-between text-sm font-medium text-slate-700">
        <span>Choose a ride</span>
        <span className="font-normal text-slate-500">
          {formatDistance(estimate.route.distance_meters)} · ~{formatDuration(estimate.route.duration_seconds)}
        </span>
      </legend>
      <div className="mt-2 grid gap-3">
        {estimate.options.map((option) => {
          const isSelected = option.category === selected;
          return (
            <label
              key={option.category}
              className={`flex cursor-pointer items-center gap-4 rounded-xl p-4 ring-1 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-600 ${
                isSelected ? 'bg-brand-50 ring-2 ring-brand-600' : 'bg-white ring-slate-200 hover:bg-slate-50'
              }`}
            >
              <input
                type="radio"
                name="category"
                value={option.category}
                checked={isSelected}
                onChange={() => onSelect(option.category)}
                className="sr-only"
              />
              <span className="grid size-10 place-items-center rounded-full bg-slate-100 text-lg" aria-hidden="true">
                {option.category === 'comfort' ? '🚘' : '🚗'}
              </span>
              <span className="flex-1">
                <span className="block font-semibold">{option.label}</span>
                <span className="block text-sm text-slate-600">
                  {option.pickup_eta_seconds !== null
                    ? `Pickup in ${formatDuration(option.pickup_eta_seconds)}`
                    : 'No drivers nearby right now'}
                </span>
              </span>
              <span className="text-right font-semibold">{formatMoney(option.fare.amount, option.fare.currency)}</span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
