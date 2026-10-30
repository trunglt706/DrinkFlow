{{-- Flash messages of the game pages: success, business-rule refusal and validation errors. --}}
@if(session('status'))
  <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-sm font-medium flex items-center gap-2.5" role="status">
    <span class="material-symbols-outlined text-[18px] text-[#006948]" aria-hidden="true">check_circle</span>
    <span>{{ session('status') }}</span>
  </div>
@endif
@if(session('error'))
  <div class="p-4 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-800 text-sm font-medium flex items-center gap-2.5" role="alert">
    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">error</span>
    <span>{{ session('error') }}</span>
  </div>
@endif
@if($errors->any())
  <div class="p-4 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-800 text-sm" role="alert">
    <ul class="space-y-1">
      @foreach($errors->all() as $message)
        <li class="flex items-start gap-2">
          <span class="material-symbols-outlined text-[16px] mt-0.5" aria-hidden="true">error</span>
          <span>{{ $message }}</span>
        </li>
      @endforeach
    </ul>
  </div>
@endif
