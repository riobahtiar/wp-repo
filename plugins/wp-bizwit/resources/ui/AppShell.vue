<script setup lang="ts">
/**
 * Lightweight page chrome for Vue islands inside a BizWit admin screen.
 * Parent markup should already sit under `.wrap.wp-bizwit`.
 */
defineProps< {
	title: string;
	description?: string;
} >();
</script>

<template>
	<section class="bw-app-shell mt-6">
		<header class="bw-app-shell__header mb-4">
			<h2 class="m-0 text-lg font-semibold text-[var(--bw-color-text)]">
				{{ title }}
			</h2>
			<p
				v-if="description"
				class="mt-1 mb-0 text-sm text-[var(--bw-color-text-muted)]"
			>
				{{ description }}
			</p>
		</header>
		<div class="bw-app-shell__body">
			<slot />
		</div>
	</section>
</template>
