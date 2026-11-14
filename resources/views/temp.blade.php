@extends('layouts.app')

@section('content')
    <p>仮の画面（一般ユーザー） :{{  Auth::user()->name  }} さんでログインy中</p>
@endsection