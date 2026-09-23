@php
    /** @var object|null $c Existing connection (edit mode) or null (create mode) */
    $c = $connection ?? null;
    $pub = $c->auth_config_public ?? [];
    $authType = old('auth_type', (string) ($c->auth_type ?? 'none'));
    $formId = 'connection-form-'.($formUid ?? ($c->id ?? 'new'));
@endphp
<form method="POST" action="{{ $action }}" class="vendor-connection-form" data-auth-form id="{{ $formId }}">@csrf
    <fieldset class="vendor-fieldset">
        <legend class="vendor-legend">{{ __('ui.conn.identity') }}</legend>
        <div class="vendor-grid-2">
            <div><label for="{{ $formId }}-label">{{ __('ui.conn.name') }}</label><input id="{{ $formId }}-label" type="text" name="label" class="form-control" value="{{ old('label', $c->label ?? __('ui.conn.default_name')) }}" placeholder="{{ __('ui.conn.name_placeholder') }}" required></div>
            <div><label for="{{ $formId }}-auth">{{ __('ui.conn.auth_method') }}</label><select id="{{ $formId }}-auth" name="auth_type" class="form-select" data-auth-type>
                <option value="none" @selected($authType === 'none')>{{ __('ui.conn.none') }}</option>
                <option value="api_key_header" @selected($authType === 'api_key_header')>{{ __('ui.conn.api_key_header') }}</option>
                <option value="bearer" @selected($authType === 'bearer')>{{ __('ui.conn.bearer') }}</option>
                <option value="basic" @selected($authType === 'basic')>{{ __('ui.conn.basic') }}</option>
                <option value="api_key_query" @selected($authType === 'api_key_query')>{{ __('ui.conn.api_key_query') }}</option>
                <option value="oauth2_client_credentials" @selected($authType === 'oauth2_client_credentials')>{{ __('ui.conn.oauth2') }}</option>
                <option value="token_login" @selected($authType === 'token_login')>{{ __('ui.conn.token_login') }}</option>
            </select></div>
        </div>
        <div class="vendor-url-field"><label for="{{ $formId }}-base">{{ __('ui.conn.base_url') }}</label><input id="{{ $formId }}-base" type="url" name="base_url" class="form-control" value="{{ old('base_url', $c->base_url ?? '') }}" placeholder="https://prodev.ut.ac.id/paramita-vendor-api/gramedia" required><small>{!! __('ui.conn.base_url_rules_full') !!}</small></div>
    </fieldset>

    <fieldset class="vendor-fieldset vendor-auth-fieldset" data-auth-fields>
        <legend class="vendor-legend">{{ __('ui.conn.auth_details') }}</legend>
        <div class="vendor-auth-fields">
            <div data-auth-field="header"><label for="{{ $formId }}-hdr">{{ __('ui.conn.header_name') }}</label><input id="{{ $formId }}-hdr" type="text" name="credential_header_name" class="form-control" value="{{ $pub['header_name'] ?? 'X-API-Key' }}"></div>
            <div data-auth-field="param"><label for="{{ $formId }}-prm">{{ __('ui.conn.query_param') }}</label><input id="{{ $formId }}-prm" type="text" name="credential_param_name" class="form-control" value="{{ $pub['param_name'] ?? 'api_key' }}"></div>
            <div data-auth-field="username"><label for="{{ $formId }}-usr">{{ __('ui.conn.service_username') }}</label><input id="{{ $formId }}-usr" type="text" name="credential_username" class="form-control" autocomplete="username" value="{{ $pub['username'] ?? '' }}" placeholder="{{ __('ui.conn.service_username_placeholder') }}"><small>{{ __('ui.conn.this_is') }} {{ __('ui.conn.service_account_note') }}</small></div>
            <div class="vendor-auth-wide" data-auth-field="login-url"><label for="{{ $formId }}-login">{{ __('ui.conn.login_url') }}</label><input id="{{ $formId }}-login" type="url" name="login_url" class="form-control" value="{{ $pub['login_url'] ?? '' }}" placeholder="https://prodev.ut.ac.id/paramita-vendor-api/gramedia/v1/auth/login"><small>{{ __('ui.conn.login_url_note') }}</small></div>
            <div data-auth-field="login-user-field"><label for="{{ $formId }}-luf">{{ __('ui.conn.username_field') }}</label><input id="{{ $formId }}-luf" type="text" name="login_username_field" class="form-control" value="{{ $pub['username_field'] ?? 'username' }}"><small>{{ __('ui.conn.username_json_note') }}</small></div>
            <div data-auth-field="login-password-field"><label for="{{ $formId }}-lpf">{{ __('ui.conn.password_field') }}</label><input id="{{ $formId }}-lpf" type="text" name="login_password_field" class="form-control" value="{{ $pub['password_field'] ?? 'password' }}"><small>{{ __('ui.conn.field_names_note') }}</small></div>
            <div data-auth-field="login-token-field"><label for="{{ $formId }}-ltf">{{ __('ui.conn.token_path') }}</label><input id="{{ $formId }}-ltf" type="text" name="login_token_field" class="form-control" value="{{ $pub['token_field'] ?? 'access_token' }}" placeholder="data.access_token"><small>{!! __('ui.conn.nested_json_note') !!}</small></div>
            <div data-auth-field="login-expiry-field"><label for="{{ $formId }}-lef">{{ __('ui.conn.expires_path') }}</label><input id="{{ $formId }}-lef" type="text" name="login_expires_in_field" class="form-control" value="{{ $pub['expires_in_field'] ?? 'expires_in' }}" placeholder="data.expires_in"><small>{{ __('ui.conn.expiry_seconds_note') }}</small></div>
            <div class="vendor-auth-wide" data-auth-field="oauth-token-url"><label for="{{ $formId }}-oturl">{{ __('ui.conn.oauth_token_url') }}</label><input id="{{ $formId }}-oturl" type="url" name="oauth_token_url" class="form-control" value="{{ $pub['token_url'] ?? '' }}" placeholder="https://auth.vendor.co.id/oauth/token"><small>{{ __('ui.conn.login_url_note') }}</small></div>
            <div data-auth-field="oauth-client-id"><label for="{{ $formId }}-ocid">{{ __('ui.conn.oauth_client_id') }}</label><input id="{{ $formId }}-ocid" type="text" name="oauth_client_id" class="form-control" value="{{ $pub['client_id'] ?? '' }}"></div>
            <div data-auth-field="oauth-scope"><label for="{{ $formId }}-oscp">{{ __('ui.conn.oauth_scope') }}</label><input id="{{ $formId }}-oscp" type="text" name="oauth_scope" class="form-control" value="{{ $pub['scope'] ?? '' }}" placeholder="inventory.read orders.read"></div>
            <div class="vendor-auth-wide" data-auth-field="secret"><label for="{{ $formId }}-sec">{{ __('ui.conn.secret') }}</label><input id="{{ $formId }}-sec" type="password" name="credential_secret" class="form-control" autocomplete="new-password" placeholder="{{ $c ? __('ui.conn.secret_saved_placeholder') : __('ui.conn.secret_placeholder') }}" @unless($c) required data-auth-secret @endunless><small>{{ $c ? __('ui.conn.secret_saved_note') : __('ui.conn.secret_note') }}</small></div>
        </div>
    </fieldset>

    <div class="vendor-form-action">
        @if($c)<button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#conn-edit-{{ $c->id }}">{{ __('ui.vendor.cancel') }}</button>@endif
        <button type="submit" class="btn btn-primary">{{ $submitLabel ?? __('ui.vendor.save_connection') }}</button>
    </div>
</form>
