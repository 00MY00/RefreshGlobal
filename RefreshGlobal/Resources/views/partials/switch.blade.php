{{--
    On/off switch of FreeScout's settings pages (same markup as resources/views/settings/general.blade.php:139-144).
    The hidden field is posted when the switch is off (an unchecked checkbox is not sent); a disabled switch keeps
    its current value. Parameters: key, label, help, disabled (optional), extra_help (optional).
--}}
<div class="form-group">
    <label for="rg-sw-{{ md5($key) }}" class="col-sm-2 control-label">{{ $label }}</label>
    <div class="col-sm-6">
        <input type="hidden" name="settings[{{ $key }}]" value="{{ !empty($disabled) && !empty($settings[$key]) ? 1 : 0 }}">
        <div class="controls">
            <div class="onoffswitch-wrap">
                <div class="onoffswitch">
                    <input type="checkbox" name="settings[{{ $key }}]" value="1" id="rg-sw-{{ md5($key) }}" class="onoffswitch-checkbox" @if (!empty($settings[$key])) checked="checked" @endif @if (!empty($disabled)) disabled @endif>
                    <label class="onoffswitch-label" for="rg-sw-{{ md5($key) }}"></label>
                </div>
            </div>
        </div>
        <p class="form-help">{{ $help }}@if (!empty($extra_help)) {{ $extra_help }}@endif</p>
    </div>
</div>
