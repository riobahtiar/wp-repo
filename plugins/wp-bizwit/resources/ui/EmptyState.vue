<script setup lang="ts">
/**
 * Empty / error panel with optional actions in the default slot.
 */
defineProps< {
	title: string;
	description?: string;
} >();
</script>

<template>
	<div
		class="bw-empty rounded-[var(--bw-radius)] border border-dashed border-[var(--bw-color-border)] bg-[var(--bw-color-surface)] px-6 py-10 text-center"
		role="status"
	>
		<h3 class="m-0 text-base font-semibold text-[var(--bw-color-text)]">
			{{ title }}
		</h3>
		<p
			v-if="description"
			class="mx-auto mt-2 mb-0 max-w-md text-sm text-[var(--bw-color-text-muted)]"
		>
			{{ description }}
		</p>
		<div
			v-if="$slots.default"
			class="mt-4 flex flex-wrap items-center justify-center gap-2"
		>
			<slot />
		</div>
	</div>
</template>
