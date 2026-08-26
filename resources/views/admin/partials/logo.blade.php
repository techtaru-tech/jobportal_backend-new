{{--
  The INTHES mark on its brand-red rounded tile.

  The glyph ships white-on-transparent and the red comes from `bg-primary`, so
  the tile stays in step with the design token instead of baking a second copy
  of the brand red into an image file.
--}}
@php $size = $size ?? 32; @endphp
<span class="flex shrink-0 items-center justify-center overflow-hidden bg-primary shadow-button {{ $size >= 40 ? 'rounded-card' : 'rounded-field' }} {{ $class ?? '' }}"
      style="width:{{ $size }}px;height:{{ $size }}px">
  {{-- Never a meaningful pause: it is the first paint of both screens it
       appears on, and it is a 19 KB local file. --}}
  <img src="{{ asset('brand/inthes-mark.png') }}" alt="" aria-hidden="true"
       width="{{ $size }}" height="{{ $size }}"
       class="h-full w-full object-contain" loading="eager" decoding="sync">
</span>
