import type { Place } from '../api/types';

interface PlaceSelectProps {
  id: string;
  label: string;
  places: Place[];
  value: Place | null;
  onChange: (place: Place | null) => void;
  disabled?: boolean;
  error?: string;
}

/** Location input backed by the server's place list (stand-in for geocoding autocomplete). */
export function PlaceSelect({ id, label, places, value, onChange, disabled, error }: PlaceSelectProps) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">
        {label}
      </label>
      <select
        id={id}
        value={value?.name ?? ''}
        disabled={disabled}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        onChange={(event) => onChange(places.find((place) => place.name === event.target.value) ?? null)}
        className="mt-1 block min-h-11 w-full rounded-xl border-0 bg-white px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-600 focus:outline-none disabled:bg-slate-100 aria-[invalid]:ring-red-400"
      >
        <option value="">Choose a place…</option>
        {places.map((place) => (
          <option key={place.name} value={place.name}>
            {place.name}
          </option>
        ))}
      </select>
      {error && (
        <p id={`${id}-error`} className="mt-1 text-xs text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}
