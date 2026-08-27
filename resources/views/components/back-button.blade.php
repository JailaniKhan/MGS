@props(['href', 'name' => 'arrow-left'])

<x-icon-button :name="$name" :href="$href" :label="__('messages.back')" {{ $attributes }}/>
