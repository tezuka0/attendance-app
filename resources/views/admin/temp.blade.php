@extends('layouts.admin-app')

@section('content')
<p>仮の画面（管理者） : {{  Auth::user()->name }} さんでログイン中</p>
@endsection