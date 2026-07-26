<script setup lang="ts">
/**
 * Domain money display — always pass amountMinor + currency, never a preformatted string.
 */
import { computed } from 'vue';

import { formatMoney } from '@/app/lib/money';

const props = defineProps< {
	amountMinor: number;
	currency: string;
} >();

const formatted = computed( () =>
	formatMoney( props.amountMinor, props.currency )
);
</script>

<template>
	<span class="bw-money tabular-nums whitespace-nowrap">{{ formatted }}</span>
</template>
