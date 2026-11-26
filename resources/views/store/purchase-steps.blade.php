<ol class="purchase-steps" aria-label="مراحل خرید">
    @foreach([1 => 'سبد خرید', 2 => 'ثبت سفارش', 3 => 'پرداخت و پیگیری'] as $step => $label)
        <li @if($step === $purchaseStep) aria-current="step" @endif @class(['step-done' => $step < $purchaseStep])>
            <span aria-hidden="true">{{ $step < $purchaseStep ? '✓' : \App\Support\PersianFormat::number($step) }}</span>
            {{ $label }}
        </li>
    @endforeach
</ol>
