<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate, formatPrice } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that matched your saved searches.">
        <ul v-if="alerts.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="alert in alerts.data" :key="alert.id">
                <p class="mb-1 text-xs text-slate-500">{{ formatDate(alert.created_at) }}</p>

                <ListingCard v-if="alert.listing.status === 'live'" :listing="alert.listing" />

                <!-- The listing page 404s once a listing is off the market, so don't link it. -->
                <article v-else class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-slate-500">
                    <span class="text-xl font-bold">{{ formatPrice(alert.listing.price) }}</span>
                    <span class="mt-1 block">
                        {{ alert.listing.address_line_1 }}, {{ alert.listing.city }}
                    </span>
                    <span class="mt-3 block text-xs font-medium uppercase">No longer available</span>
                </article>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. You'll see new listings here when they match a saved search.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
