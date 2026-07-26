<script setup lang="ts">
/**
 * Dashboard pilot island — proves PHP → enqueue → Vue → REST → design system.
 *
 * User-facing copy uses @wordpress/i18n so strings follow the site locale
 * (English source → languages/*.json / PHP translations).
 */
import { onMounted, ref } from 'vue';

import { apiGet } from '@/app/api/client';
import { __ } from '@/app/i18n';
import AppShell from '@ui/AppShell.vue';
import Button from '@ui/Button.vue';
import EmptyState from '@ui/EmptyState.vue';
import MoneyText from '@ui/MoneyText.vue';

interface HealthResponse {
	ok: boolean;
	version: string;
	region: string;
}

type LoadState = 'loading' | 'ready' | 'error';

const state = ref< LoadState >( 'loading' );
const health = ref< HealthResponse | null >( null );
const errorMessage = ref( '' );

const currency =
	typeof window !== 'undefined' && window.wpBizwitConfig?.currency
		? window.wpBizwitConfig.currency
		: 'IDR';

/** Sample amount for MoneyText pilot (Rp 1.500.000 when currency is IDR). */
const demoAmountMinor = 1_500_000;

async function loadHealth(): Promise< void > {
	state.value = 'loading';
	errorMessage.value = '';
	health.value = null;

	try {
		const data = await apiGet< HealthResponse >( 'health' );
		health.value = data;
		state.value = 'ready';
	} catch ( err ) {
		errorMessage.value =
			err instanceof Error
				? err.message
				: __( 'Could not load system status.', 'wp-bizwit' );
		state.value = 'error';
	}
}

onMounted( () => {
	void loadHealth();
} );
</script>

<template>
	<AppShell
		:title="__( 'System status', 'wp-bizwit' )"
		:description="
			__(
				'Vue pilot panel — verifies REST and money formatting.',
				'wp-bizwit'
			)
		"
	>
		<!-- Loading skeleton -->
		<div
			v-if="state === 'loading'"
			class="rounded-[var(--bw-radius)] border border-[var(--bw-color-border)] bg-[var(--bw-color-surface)] p-5"
			aria-busy="true"
			aria-live="polite"
		>
			<p class="m-0 mb-4 text-sm text-[var(--bw-color-text-muted)]">
				{{ __( 'Loading…', 'wp-bizwit' ) }}
			</p>
			<div class="flex flex-col gap-3">
				<div
					class="h-4 w-2/5 max-w-xs animate-pulse rounded bg-[var(--bw-color-surface-muted)]"
				/>
				<div
					class="h-4 w-1/3 max-w-[12rem] animate-pulse rounded bg-[var(--bw-color-surface-muted)]"
				/>
				<div
					class="h-4 w-1/2 max-w-sm animate-pulse rounded bg-[var(--bw-color-surface-muted)]"
				/>
			</div>
		</div>

		<!-- Error -->
		<EmptyState
			v-else-if="state === 'error'"
			:title="__( 'Could not load status', 'wp-bizwit' )"
			:description="
				errorMessage ||
				__(
					'Please try again. Make sure you are still signed in and have a BizWit capability.',
					'wp-bizwit'
				)
			"
		>
			<Button variant="primary" type="button" @click="loadHealth">
				{{ __( 'Try again', 'wp-bizwit' ) }}
			</Button>
		</EmptyState>

		<!-- Ready -->
		<div
			v-else-if="state === 'ready' && health"
			class="rounded-[var(--bw-radius)] border border-[var(--bw-color-border)] bg-[var(--bw-color-surface)] p-5 shadow-sm"
		>
			<dl class="m-0 grid gap-3 text-sm sm:grid-cols-2">
				<div>
					<dt class="m-0 font-medium text-[var(--bw-color-text-muted)]">
						{{ __( 'Version', 'wp-bizwit' ) }}
					</dt>
					<dd class="m-0 mt-0.5 text-[var(--bw-color-text)]">
						{{ health.version }}
					</dd>
				</div>
				<div>
					<dt class="m-0 font-medium text-[var(--bw-color-text-muted)]">
						{{ __( 'Region', 'wp-bizwit' ) }}
					</dt>
					<dd class="m-0 mt-0.5 text-[var(--bw-color-text)]">
						{{ health.region }}
					</dd>
				</div>
				<div class="sm:col-span-2">
					<dt class="m-0 font-medium text-[var(--bw-color-text-muted)]">
						{{ __( 'Money format example', 'wp-bizwit' ) }}
					</dt>
					<dd class="m-0 mt-0.5 text-base text-[var(--bw-color-text)]">
						<MoneyText
							:amount-minor="demoAmountMinor"
							:currency="currency"
						/>
						<span
							class="ml-2 text-xs text-[var(--bw-color-text-muted)]"
						>
							({{ demoAmountMinor }} minor · {{ currency }})
						</span>
					</dd>
				</div>
			</dl>
		</div>
	</AppShell>
</template>
