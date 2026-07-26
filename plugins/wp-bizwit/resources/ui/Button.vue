<script setup lang="ts">
/**
 * Primary action control for BizWit Vue screens.
 * Always a real <button> (never a div) for keyboard and form semantics.
 */
withDefaults(
	defineProps< {
		variant?: 'primary' | 'secondary';
		size?: 'sm' | 'md' | 'lg';
		disabled?: boolean;
		type?: 'button' | 'submit' | 'reset';
	} >(),
	{
		variant: 'primary',
		size: 'md',
		disabled: false,
		type: 'button',
	}
);
</script>

<template>
	<button
		:type="type"
		:disabled="disabled"
		class="bw-btn inline-flex items-center justify-center gap-1.5 rounded font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
		:class="[
			variant === 'primary'
				? 'bg-[var(--bw-color-primary)] text-white hover:bg-[var(--bw-color-primary-hover)] focus-visible:outline-[var(--bw-color-primary)]'
				: 'border border-[var(--bw-color-border)] bg-white text-[var(--bw-color-text)] hover:bg-[var(--bw-color-surface-muted)] focus-visible:outline-[var(--bw-color-primary)]',
			size === 'sm' && 'px-2.5 py-1 text-xs',
			size === 'md' && 'px-3.5 py-1.5 text-sm',
			size === 'lg' && 'px-4 py-2 text-base',
		]"
	>
		<slot />
	</button>
</template>
