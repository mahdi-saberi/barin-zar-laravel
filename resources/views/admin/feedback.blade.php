@if(session('notice'))
    <p class="admin-message admin-message-success" role="status">{{ session('notice') }}</p>
@endif
@if($errors->any())
    <ul class="admin-message admin-message-error" role="alert">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
