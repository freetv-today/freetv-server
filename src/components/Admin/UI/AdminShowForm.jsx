import { useState, useEffect } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils';

/**
 * AdminShowForm - Reusable form for editing/adding a show
 * @param {Object} props
 * @param {Object} [props.initialData] - Show data to edit (optional)
 * @param {function(Object):void} props.onSave - Called with show data on save
 * @param {function():void} props.onCancel - Called on cancel
 * @param {boolean} [props.saving] - Whether save is in progress
 * @param {string|null} [props.error] - Error message
 * @param {Array<string>} [props.categories] - List of categories for select
 */
export function AdminShowForm({ initialData = {}, onSave, onCancel, saving = false, error = null, categories = [] }) {
  const [form, setForm] = useState({
    category: initialData.category || '',
    status: initialData.status || 'active',
    identifier: initialData.identifier || '',
    title: initialData.title || '',
    desc: initialData.desc || '',
    start: initialData.start || '',
    end: initialData.end || '',
    imdb: initialData.imdb || ''
  });
  const [touched, setTouched] = useState(false);
  const [validation, setValidation] = useState({
    category: '',
    status: '',
    identifier: '',
    title: '',
    desc: '',
    start: '',
    end: '',
    imdb: ''
  });

  useEffect(() => {
    setForm({
      category: initialData.category || '',
      status: initialData.status || 'active',
      identifier: initialData.identifier || '',
      title: initialData.title || '',
      desc: initialData.desc || '',
      start: initialData.start || '',
      end: initialData.end || '',
      imdb: initialData.imdb || ''
    });
    setTouched(false);
    setValidation({
      category: '',
      status: '',
      identifier: '',
      title: '',
      desc: '',
      start: '',
      end: '',
      imdb: ''
    });
  }, [initialData]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(f => ({ ...f, [name]: value }));
    setTouched(true);
  }

  function handleStatusToggle(val) {
    setForm(f => ({ ...f, status: val }));
    setTouched(true);
  }

  function validate() {
    const v = {};
    // All fields required
    for (const key of ['category', 'status', 'identifier', 'title', 'desc', 'start', 'end', 'imdb']) {
      if (!form[key] || String(form[key]).trim() === '') v[key] = 'Required';
    }
    if (form.desc && form.desc.length > 255) v.desc = 'Description must be 255 characters or less';
    // Start/end: 4-digit years
    if (form.start && !/^\d{4}$/.test(form.start)) v.start = 'Must be 4-digit year';
    if (form.end && !/^\d{4}$/.test(form.end)) v.end = 'Must be 4-digit year';
    // Chronological order
    if (form.start && form.end && /^\d{4}$/.test(form.start) && /^\d{4}$/.test(form.end)) {
      if (parseInt(form.end) < parseInt(form.start)) {
        v.end = 'End year cannot be earlier than start year';
        v.start = 'Start year cannot be later than end year';
      }
    }
    setValidation({
      category: v.category || '',
      status: v.status || '',
      identifier: v.identifier || '',
      title: v.title || '',
      desc: v.desc || '',
      start: v.start || '',
      end: v.end || '',
      imdb: v.imdb || ''
    });
    return Object.keys(v).length === 0;
  }

  function handleSubmit(e) {
    e.preventDefault();
    if (validate()) {
      onSave(form);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mb-5">
      <div className="mb-3 w-50">
        <label className="form-label fw-bold">Category</label>
        <select
          className="form-select form-select-sm"
          name="category"
          value={form.category}
          onInput={handleChange}
          required
        >
          <option value="">Select category...</option>
          {categories.map(cat => (
            <option value={cat} key={cat}>{capitalizeFirstLetter(cat)}</option>
          ))}
        </select>
        {validation.category && <div className="text-danger small">{validation.category}</div>}
      </div>
      <div className="mb-3">
        <label className="form-label fw-bold">Status</label>
        <div className="btn-group ms-2" role="group">
          <input type="radio" className="btn-check" name="status" id="statusActive" autoComplete="off" checked={form.status === 'active'} onChange={() => handleStatusToggle('active')} />
          <label className={`btn btn-sm btn-outline-success${form.status === 'active' ? ' active' : ''}`} htmlFor="statusActive">Active</label>
          <input type="radio" className="btn-check" name="status" id="statusDisabled" autoComplete="off" checked={form.status === 'disabled'} onChange={() => handleStatusToggle('disabled')} />
          <label className={`btn btn-sm btn-outline-danger${form.status === 'disabled' ? ' active' : ''}`} htmlFor="statusDisabled">Disabled</label>
        </div>
        {validation.status && <div className="text-danger small">{validation.status}</div>}
      </div>
      <div className="mb-3">
        <label className="form-label fw-bold">Identifier</label>
        <input type="text" className="form-control form-control-sm" name="identifier" value={form.identifier} onInput={handleChange} required />
        {validation.identifier && <div className="text-danger small">{validation.identifier}</div>}
      </div>
      <div className="mb-3">
        <label className="form-label fw-bold">Title</label>
        <input type="text" className="form-control form-control-sm" name="title" value={form.title} onInput={handleChange} required />
        {validation.title && <div className="text-danger small">{validation.title}</div>}
      </div>
      <div className="mb-2">
        <label className="form-label fw-bold">Description</label>
        <textarea className="form-control form-control-sm" name="desc" value={form.desc} onInput={handleChange} required rows={3} maxlength={255} />
        {validation.desc && <div className="text-danger small">{validation.desc}</div>}
      </div>
      <div className="row w-50">
        <div className="col mt-2">
          <label className="form-label fw-bold">Start Year</label>
          <input type="text" className="form-control form-control-sm yearfield" name="start" value={form.start} onInput={handleChange} required maxLength={4} />
          {validation.start && <div className="text-danger small">{validation.start}</div>}
        </div>
        <div className="col mt-2">
          <label className="form-label fw-bold">End Year</label>
          <input type="text" className="form-control form-control-sm yearfield" name="end" value={form.end} onInput={handleChange} required maxLength={4} />
          {validation.end && <div className="text-danger small">{validation.end}</div>}
        </div>
      </div>
      <div className="mb-3 mt-3 w-50">
        <label className="form-label fw-bold">IMDB ID</label>
        <input type="text" className="form-control form-control-sm" name="imdb" value={form.imdb} onInput={handleChange} required />
        {validation.imdb && <div className="text-danger small">{validation.imdb}</div>}
      </div>
      {error && <div className="alert alert-danger">{error}</div>}
      <div className="mt-5 d-flex justify-content-center gap-2">
        <button type="button" className="btn btn-secondary" onClick={onCancel} disabled={saving}>Cancel</button>
        <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving...' : 'Save'}</button>
      </div>
    </form>
  );
}
