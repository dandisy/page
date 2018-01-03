@extends('themes::master')

@section('content')    
  <section id="content">    
    @include('themes::position.top')

    <div class="wrapper pad1">
      @include('themes::position.left')
      
      @include('themes::position.main_right')
    </div>
  </section>
@endsection