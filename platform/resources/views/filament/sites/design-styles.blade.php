@once
<style>
    .dz-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
    .dz-option { display: flex; flex-direction: column; gap: 6px; cursor: pointer; }
    .dz-option input { position: absolute; opacity: 0; pointer-events: none; }
    .dz-thumb { overflow: hidden; border-radius: 8px; border: 2px solid rgb(0 0 0 / 10%); background: #fff; transition: border-color .15s, box-shadow .15s; }
    .dz-option:hover .dz-thumb { border-color: rgb(0 0 0 / 25%); }
    .dz-option:has(input:checked) .dz-thumb { border-color: var(--primary-600, #d97706); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-600, #d97706) 25%, transparent); }
    .dz-option:has(input:focus-visible) .dz-thumb { outline: 2px solid var(--primary-600, #d97706); outline-offset: 2px; }
    .dz-label { font-size: 12.5px; line-height: 1.3; }
    .dz-option:has(input:checked) .dz-label { font-weight: 600; }
    .dz-themes { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
    .dz-theme { display: flex; flex-direction: column; overflow: hidden; border-radius: 12px; border: 1px solid rgb(0 0 0 / 10%); background: var(--color-white, #fff); }
    .dark .dz-theme { background: rgb(255 255 255 / 4%); border-color: rgb(255 255 255 / 10%); }
    .dz-theme.is-current { border-color: var(--primary-600, #d97706); box-shadow: 0 0 0 2px var(--primary-600, #d97706); }
    .dz-theme-thumb { max-height: 200px; overflow: hidden; border-bottom: 1px solid rgb(0 0 0 / 8%); }
    .dz-theme-body { display: flex; flex-direction: column; gap: 6px; flex: 1; padding: 12px 14px 14px; }
    .dz-theme-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-weight: 600; }
    .dz-theme-meta { font-size: 12px; opacity: .7; }
    .dz-theme-text { font-size: 13px; margin: 0; }
    .dz-theme-actions { display: flex; gap: 8px; margin-top: auto; padding-top: 8px; }
    .dz-preview { overflow: hidden; border-radius: 10px; border: 1px solid rgb(0 0 0 / 10%); max-height: 70vh; overflow-y: auto; }
</style>
@endonce
