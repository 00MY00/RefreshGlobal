{{-- The filters shown, posted with "Save" / "Update view" (cleaned again by GlobalTicketQuery::normalize()). --}}
@foreach ($filters['mailboxes'] as $id)<input type="hidden" name="mb[]" value="{{ $id }}">@endforeach
@foreach ($filters['status'] as $code)<input type="hidden" name="status[]" value="{{ $code }}">@endforeach
<input type="hidden" name="assignee" value="{{ $filters['assignee'] }}">
<input type="hidden" name="q" value="{{ $filters['q'] }}">
@if ($filters['rview'] !== '')<input type="hidden" name="rv" value="{{ $filters['rview'] }}">@endif
@if ($filters['wfolder'] !== '')<input type="hidden" name="wf" value="{{ $filters['wfolder'] }}">@endif
@if ($filters['mailtag'] !== '')<input type="hidden" name="mt" value="{{ $filters['mailtag'] }}">@endif
<input type="hidden" name="sort" value="{{ $filters['sort'] }}">
<input type="hidden" name="order" value="{{ $filters['order'] }}">
