<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import FormErrors from '../../components/FormErrors.vue';
import ListingFilters from '../../components/ListingFilters.vue';
import { formatPrice } from '../../format';

defineProps({
    savedSearches: { type: Array, required: true },
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
});

const emptyForm = () => ({ property_type: '', region: '', min_bedrooms: '', max_price: '' });

const form = ref(emptyForm());
const processing = ref(false);

function save() {
    router.post('/saved-searches', form.value, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (form.value = emptyForm()),
    });
}

function describe(search) {
    return [
        search.property_type_label,
        search.min_bedrooms !== null && `${search.min_bedrooms}+ beds`,
        search.max_price !== null && `up to ${formatPrice(search.max_price)}`,
        search.region && `in ${search.region}`,
    ]
        .filter(Boolean)
        .join(', ');
}

// The criteria are the listings index filters, so this shows what's live now.
function currentMatchesUrl(search) {
    const query = Object.fromEntries(
        ['property_type', 'min_bedrooms', 'max_price', 'region']
            .filter((key) => search[key] !== null)
            .map((key) => [key, search[key]]),
    );

    return `/?${new URLSearchParams(query)}`;
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout
        heading="Saved searches"
        subheading="We'll alert you when a new listing matches one of these."
    >
        <ListingFilters
            v-model="form"
            :branches="branches"
            :property-types="propertyTypes"
            :processing="processing"
            submit-label="Save search"
            @submit="save"
            @reset="form = emptyForm()"
        />

        <FormErrors :errors="errors" />

        <ul v-if="savedSearches.length" class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
            <li
                v-for="search in savedSearches"
                :key="search.id"
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <span class="text-slate-900">{{ describe(search) }}</span>

                <span class="flex gap-3 text-sm">
                    <Link :href="currentMatchesUrl(search)" class="text-slate-600 underline-offset-2 hover:underline">
                        View current matches
                    </Link>
                    <Link
                        :href="`/saved-searches/${search.id}`"
                        method="delete"
                        as="button"
                        preserve-scroll
                        class="text-red-700 underline-offset-2 hover:underline"
                    >
                        Delete
                    </Link>
                </span>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No saved searches yet. Pick some criteria above to save one.
        </p>
    </AppLayout>
</template>
