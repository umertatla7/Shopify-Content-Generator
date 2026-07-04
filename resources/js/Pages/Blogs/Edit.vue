<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Bold,
    CalendarClock,
    CheckCircle,
    Eraser,
    Heading2,
    Heading1,
    Heading3,
    Image,
    Italic,
    Link2,
    List,
    ListOrdered,
    ImagePlus,
    LoaderCircle,
    Minus,
    Pilcrow,
    Quote,
    Redo2,
    RefreshCw,
    RotateCcw,
    Save,
    Send,
    Sparkles,
    Underline,
    Undo2,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps({
    blog: Object,
});

const inferWordTarget = (value) => {
    const matches = String(value || '').match(/\d[\d,]*/g) ?? [];
    const numbers = matches
        .map((item) => Number(String(item).replaceAll(',', '')))
        .filter((item) => Number.isFinite(item) && item > 0);

    if (!numbers.length) {
        return 1200;
    }

    return Math.max(300, Math.min(1500, numbers[numbers.length - 1]));
};

const editor = ref(null);
const secondaryKeywordsText = ref((props.blog.secondary_keywords ?? []).join('\n'));
const faqText = ref(JSON.stringify(props.blog.faq ?? [], null, 2));
const internalLinksText = ref(JSON.stringify(props.blog.internal_links ?? [], null, 2));
const productLinksText = ref(JSON.stringify(props.blog.product_links ?? [], null, 2));

const form = useForm({
    title: props.blog.title ?? '',
    meta_title: props.blog.meta_title ?? '',
    meta_description: props.blog.meta_description ?? '',
    slug: props.blog.slug ?? '',
    excerpt: props.blog.excerpt ?? '',
    body: props.blog.body ?? '',
    featured_image_idea: props.blog.featured_image_idea ?? '',
    featured_image_prompt: props.blog.featured_image_prompt ?? '',
    featured_image_alt: props.blog.featured_image_alt ?? '',
    featured_image_url: props.blog.featured_image_url ?? '',
    primary_keyword: props.blog.primary_keyword ?? '',
    secondary_keywords: props.blog.secondary_keywords ?? [],
    faq: props.blog.faq ?? [],
    internal_links: props.blog.internal_links ?? [],
    product_links: props.blog.product_links ?? [],
    target_word_count: props.blog.payload?.target_word_count ?? inferWordTarget(props.blog.topic?.estimated_article_size),
    status: props.blog.status ?? 'draft',
});

const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const storeTimezone = computed(() => props.blog.store?.timezone || browserTimezone);
const zonedDateTimeInput = (value, timezone) => {
    if (!value) return '';

    try {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date(value)).reduce((carry, part) => {
            carry[part.type] = part.value;
            return carry;
        }, {});

        return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
    } catch {
        return String(value).slice(0, 16);
    }
};
const commentForm = useForm({ body: '' });
const scheduleForm = useForm({
    scheduled_for: zonedDateTimeInput(props.blog.scheduled_at, storeTimezone.value),
    timezone: storeTimezone.value,
    recurrence_rule: '',
});

const bodyGenerating = ref(false);
const bodyTyping = ref(false);
const bodyGenerationError = ref('');
const bodyProgress = ref(0);
const bodyProgressLabel = ref('Preparing article');
const bodyToneOptions = [
    'Luxury',
    'Professional',
    'Friendly',
    'Educational',
    'Conversational',
    'Persuasive',
    'Premium',
    'Minimal',
];
const confirmedTone = ref(props.blog.topic?.tone || props.blog.store?.brand_tone || 'Professional');
const seoScore = ref(props.blog.seo_score);
const readabilityScore = ref(props.blog.readability_score);
const blogStatus = ref(props.blog.status ?? 'draft');
const bodyBusy = computed(() => bodyGenerating.value || bodyTyping.value);
const publishableStatuses = ['approved', 'scheduled', 'published'];
const canSendToShopify = computed(() => publishableStatuses.includes(blogStatus.value));
const hasPublishableBody = computed(() => plainBodyText.value.length > 0);
const canPublishToShopify = computed(() => canSendToShopify.value && hasPublishableBody.value && !form.processing);
const publishButtonLabel = computed(() => blogStatus.value === 'published' ? 'Update Shopify' : 'Publish');
const plainBodyText = computed(() => (form.body || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim());
const bodyWordCount = computed(() => plainBodyText.value ? plainBodyText.value.split(' ').filter(Boolean).length : 0);
const readingMinutes = computed(() => Math.max(1, Math.ceil(bodyWordCount.value / 220)));
const scheduledStoreTime = computed(() => {
    if (!props.blog.scheduled_at) return null;

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'long',
            timeStyle: 'short',
            timeZone: storeTimezone.value,
        }).format(new Date(props.blog.scheduled_at));
    } catch {
        return new Date(props.blog.scheduled_at).toLocaleString();
    }
});
let bodyProgressTimer = null;

const parseJson = (value, fallback) => {
    try {
        return JSON.parse(value || 'null') ?? fallback;
    } catch {
        return fallback;
    }
};

const syncBody = () => {
    form.body = editor.value?.innerHTML ?? form.body;
};

onMounted(() => {
    if (editor.value) {
        editor.value.innerHTML = form.body || '';
    }
});

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const startBodyProgress = () => {
    const labels = [
        'Studying store knowledge',
        'Building article structure',
        'Writing H2 sections',
        'Adding product context',
        'Preparing FAQs and links',
    ];
    let step = 0;
    bodyProgress.value = 12;
    bodyProgressLabel.value = labels[step];
    clearInterval(bodyProgressTimer);
    bodyProgressTimer = setInterval(() => {
        step = Math.min(step + 1, labels.length - 1);
        bodyProgressLabel.value = labels[step];
        bodyProgress.value = Math.min(bodyProgress.value + 14, 86);
    }, 1200);
};

const finishBodyProgress = (label = 'Writing into editor') => {
    clearInterval(bodyProgressTimer);
    bodyProgressTimer = null;
    bodyProgress.value = 100;
    bodyProgressLabel.value = label;
};

const splitHtmlBlocks = (html) => html.match(/<(h1|h2|h3|p|ul|ol|blockquote)[^>]*>[\s\S]*?<\/\1>/gi) ?? [html];

const typeBodyIntoEditor = async (html) => {
    bodyTyping.value = true;
    form.body = '';
    await nextTick();

    if (editor.value) {
        editor.value.innerHTML = '';
    }

    for (const block of splitHtmlBlocks(html)) {
        form.body += block;

        if (editor.value) {
            editor.value.innerHTML = form.body;
            editor.value.scrollTop = editor.value.scrollHeight;
        }

        await sleep(140);
    }

    syncBody();
    bodyTyping.value = false;
};

const command = async (name, value = null) => {
    editor.value?.focus();
    document.execCommand(name, false, value);
    await nextTick();
    syncBody();
};

const addLink = () => {
    const url = prompt('URL');
    if (url) command('createLink', url);
};

const addImage = () => {
    const url = prompt('Image URL');
    if (url) command('insertImage', url);
};

const prepareForm = () => {
    syncBody();
    form.secondary_keywords = secondaryKeywordsText.value.split('\n').map((item) => item.trim()).filter(Boolean);
    form.faq = parseJson(faqText.value, []);
    form.internal_links = parseJson(internalLinksText.value, []);
    form.product_links = parseJson(productLinksText.value, []);
};

const persistBlog = (options = {}) => {
    prepareForm();
    form.patch(`/blogs/${props.blog.id}`, {
        preserveScroll: true,
        onSuccess: options.onSuccess,
    });
};

const save = () => persistBlog();

const workflowStatus = {
    approve: 'approved',
    reject: 'rejected',
    'needs-review': 'needs_review',
};
const workflow = (action) => persistBlog({
    onSuccess: () => router.post(`/blogs/${props.blog.id}/${action}`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (workflowStatus[action]) {
                blogStatus.value = workflowStatus[action];
                form.status = workflowStatus[action];
            }
        },
    }),
});
const publish = () => persistBlog({
    onSuccess: () => router.post(`/blogs/${props.blog.id}/publish`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            blogStatus.value = 'published';
            form.status = 'published';
        },
    }),
});
const generateFullBody = async () => {
    syncBody();
    form.secondary_keywords = secondaryKeywordsText.value.split('\n').map((item) => item.trim()).filter(Boolean);
    bodyGenerationError.value = '';
    bodyGenerating.value = true;
    startBodyProgress();

    try {
        const response = await window.axios.post(`/blogs/${props.blog.id}/generate-body`, {
            title: form.title,
            meta_title: form.meta_title,
            meta_description: form.meta_description,
            slug: form.slug,
            excerpt: form.excerpt,
            featured_image_idea: form.featured_image_idea,
            primary_keyword: form.primary_keyword,
            secondary_keywords: form.secondary_keywords,
            tone: confirmedTone.value,
            target_word_count: form.target_word_count,
        }, { headers: { Accept: 'application/json' } });

        const payload = response.data;
        finishBodyProgress();

        form.faq = payload.faq ?? [];
        form.internal_links = payload.internal_links ?? [];
        form.product_links = payload.product_links ?? [];
        form.featured_image_idea = payload.featured_image_idea ?? form.featured_image_idea;
        faqText.value = JSON.stringify(form.faq, null, 2);
        internalLinksText.value = JSON.stringify(form.internal_links, null, 2);
        productLinksText.value = JSON.stringify(form.product_links, null, 2);
        seoScore.value = payload.seo_score ?? seoScore.value;
        readabilityScore.value = payload.readability_score ?? readabilityScore.value;
        blogStatus.value = payload.status ?? blogStatus.value;

        await typeBodyIntoEditor(payload.body ?? '');
    } catch (error) {
        finishBodyProgress('Could not generate article');
        const errors = error.response?.data?.errors;
        const firstValidationError = errors ? Object.values(errors).flat()[0] : null;
        bodyGenerationError.value = error.response?.data?.message
            ?? firstValidationError
            ?? error.message
            ?? 'Full blog body generation failed.';
    } finally {
        bodyGenerating.value = false;
        setTimeout(() => {
            if (!bodyBusy.value) {
                bodyProgress.value = 0;
            }
        }, 900);
    }
};
const schedule = () => scheduleForm.post(`/blogs/${props.blog.id}/schedule`, { preserveScroll: true });
const addComment = () => commentForm.post(`/blogs/${props.blog.id}/comments`, {
    preserveScroll: true,
    onSuccess: () => commentForm.reset(),
});
const syncFromShopify = () => router.post(`/blogs/${props.blog.id}/sync-shopify`, {}, { preserveScroll: true });
const createImageBrief = () => router.post(`/blogs/${props.blog.id}/image`, {}, { preserveScroll: true });
</script>

<template>
    <Head :title="`Edit ${props.blog.title}`" />
    <AppLayout>
        <template #title>Blog Editor</template>

        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <Link href="/blogs" class="text-sm font-semibold text-teal-700">Back to blogs</Link>
                <h2 class="mt-1 text-2xl font-bold text-zinc-950">{{ props.blog.title }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ props.blog.store?.name }} · {{ props.blog.primary_keyword ?? 'No keyword' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="badge" :class="`badge-${blogStatus}`">{{ blogStatus.replace('_', ' ') }}</span>
                <button class="btn btn-secondary" @click="workflow('needs-review')"><RotateCcw class="size-4" />Review</button>
                <button class="btn btn-secondary" @click="workflow('approve')"><CheckCircle class="size-4" />Approve</button>
                <button class="btn btn-danger" @click="workflow('reject')"><XCircle class="size-4" />Reject</button>
                <button
                    v-if="props.blog.shopify_article_id"
                    class="btn btn-secondary"
                    title="Pull the latest article content from Shopify"
                    @click="syncFromShopify"
                >
                    <RefreshCw class="size-4" />Sync from Shopify
                </button>
                <button
                    class="btn btn-primary"
                    :disabled="!canPublishToShopify"
                    :title="!canSendToShopify
                        ? 'Approve the blog before publishing'
                        : (!hasPublishableBody ? 'Generate and save the full blog body before publishing' : 'Send saved content to Shopify')"
                    @click="publish"
                >
                    <Send class="size-4" />{{ publishButtonLabel }}
                </button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="space-y-6">
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">SEO content</h3>
                        <button class="btn btn-primary" :disabled="form.processing" @click="save"><Save class="size-4" />Save</button>
                    </div>
                    <div class="panel-body space-y-4">
                        <div>
                            <label>Title</label>
                            <input v-model="form.title" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label>Meta title</label>
                                <input v-model="form.meta_title" />
                            </div>
                            <div>
                                <label>URL slug</label>
                                <input v-model="form.slug" />
                            </div>
                        </div>
                        <div>
                            <label>Meta description</label>
                            <textarea v-model="form.meta_description" class="min-h-20" />
                        </div>
                        <div>
                            <label>Excerpt</label>
                            <textarea v-model="form.excerpt" class="min-h-20" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label>Primary keyword</label>
                                <input v-model="form.primary_keyword" />
                            </div>
                            <div>
                                <label>Secondary keywords</label>
                                <textarea v-model="secondaryKeywordsText" class="min-h-20" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">Featured image</h3>
                        <button class="btn btn-secondary" @click="createImageBrief"><ImagePlus class="size-4" />Create brief</button>
                    </div>
                    <div class="panel-body space-y-4">
                        <div>
                            <label>Image idea</label>
                            <textarea v-model="form.featured_image_idea" class="min-h-20" />
                        </div>
                        <div>
                            <label>Image generation prompt</label>
                            <textarea v-model="form.featured_image_prompt" class="min-h-28" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label>Alt text</label>
                                <input v-model="form.featured_image_alt" />
                            </div>
                            <div>
                                <label>Generated image URL</label>
                                <input v-model="form.featured_image_url" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between rounded-md border border-zinc-200 p-3 text-sm">
                            <span class="text-zinc-500">Image status</span>
                            <span class="badge" :class="`badge-${props.blog.featured_image_status ?? 'pending'}`">{{ props.blog.featured_image_status ?? 'pending' }}</span>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="text-sm font-bold text-zinc-950">Body</h3>
                            <p class="text-xs text-zinc-500">Generate the full article after the title, meta fields, keywords, and topic are ready.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="rounded-md bg-zinc-100 px-3 py-2 text-xs font-semibold text-zinc-600">
                                {{ bodyWordCount.toLocaleString() }} words · {{ readingMinutes }} min read
                            </div>
                            <div class="flex min-w-44 items-center gap-2">
                                <label class="shrink-0 text-xs font-semibold uppercase text-zinc-500">Words</label>
                                <select v-model="form.target_word_count" class="h-9 py-1 text-sm" title="Choose target word count">
                                    <option :value="600">600</option>
                                    <option :value="800">800</option>
                                    <option :value="1000">1000</option>
                                    <option :value="1200">1200</option>
                                    <option :value="1500">1500</option>
                                </select>
                            </div>
                            <div class="flex min-w-52 items-center gap-2">
                                <label class="shrink-0 text-xs font-semibold uppercase text-zinc-500">Tone</label>
                                <select v-model="confirmedTone" class="h-9 py-1 text-sm" title="Confirm blog tone before generating">
                                    <option v-for="tone in bodyToneOptions" :key="tone" :value="tone">{{ tone }}</option>
                                </select>
                            </div>
                            <button class="btn btn-primary" :disabled="bodyBusy || form.processing" @click="generateFullBody">
                                <LoaderCircle v-if="bodyBusy" class="size-4 animate-spin" />
                                <Sparkles v-else class="size-4" />
                                {{ bodyTyping ? 'Writing...' : 'Generate full body' }}
                            </button>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="mb-3 flex flex-wrap items-center gap-1 rounded-md border border-zinc-200 bg-zinc-50 p-2">
                            <button class="editor-tool" type="button" title="Undo" @click="command('undo')"><Undo2 class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Redo" @click="command('redo')"><Redo2 class="size-4" /></button>
                            <span class="mx-1 h-6 w-px bg-zinc-200" />
                            <button class="editor-tool" type="button" title="Paragraph" @click="command('formatBlock', 'p')"><Pilcrow class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Heading 1" @click="command('formatBlock', 'h1')"><Heading1 class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Heading 2" @click="command('formatBlock', 'h2')"><Heading2 class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Heading 3" @click="command('formatBlock', 'h3')"><Heading3 class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Quote" @click="command('formatBlock', 'blockquote')"><Quote class="size-4" /></button>
                            <span class="mx-1 h-6 w-px bg-zinc-200" />
                            <button class="editor-tool" type="button" title="Bold" @click="command('bold')"><Bold class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Italic" @click="command('italic')"><Italic class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Underline" @click="command('underline')"><Underline class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Clear formatting" @click="command('removeFormat')"><Eraser class="size-4" /></button>
                            <span class="mx-1 h-6 w-px bg-zinc-200" />
                            <button class="editor-tool" type="button" title="Bullet list" @click="command('insertUnorderedList')"><List class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Numbered list" @click="command('insertOrderedList')"><ListOrdered class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Link" @click="addLink"><Link2 class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Image" @click="addImage"><Image class="size-4" /></button>
                            <button class="editor-tool" type="button" title="Divider" @click="command('insertHorizontalRule')"><Minus class="size-4" /></button>
                        </div>
                        <div v-if="bodyBusy" class="mb-4 rounded-md border border-teal-200 bg-teal-50 p-4">
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                <div class="flex items-center gap-2 font-semibold text-teal-900">
                                    <LoaderCircle class="size-4 animate-spin" />
                                    {{ bodyProgressLabel }}
                                </div>
                                <span class="text-xs font-semibold text-teal-800">{{ bodyProgress }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-white">
                                <div class="h-full rounded-full bg-teal-700 transition-all duration-500" :style="{ width: `${bodyProgress}%` }" />
                            </div>
                            <p class="mt-2 text-xs text-teal-800">The article will appear in the editor as soon as AI finishes the draft.</p>
                        </div>
                        <div v-if="bodyGenerationError" class="mb-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
                            {{ bodyGenerationError }}
                        </div>
                        <div v-if="!form.body" class="mb-4 rounded-md border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-600">
                            This draft has SEO metadata but no full article body yet. Click <span class="font-semibold text-zinc-950">Generate full body</span> to write the complete blog content.
                        </div>
                        <div v-else-if="!hasPublishableBody" class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            The article body is still empty after formatting cleanup. Add body content before sending this blog to Shopify.
                        </div>
                        <div
                            ref="editor"
                            class="blog-editor min-h-[520px] rounded-md border border-zinc-300 bg-white p-6 text-base leading-8 text-zinc-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                            contenteditable="true"
                            data-placeholder="Write or paste the full blog body here."
                            @input="syncBody"
                        />
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="panel">
                        <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">FAQ</h3></div>
                        <div class="panel-body"><textarea v-model="faqText" class="font-mono text-xs" /></div>
                    </div>
                    <div class="panel">
                        <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Internal links</h3></div>
                        <div class="panel-body"><textarea v-model="internalLinksText" class="font-mono text-xs" /></div>
                    </div>
                    <div class="panel">
                        <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Product links</h3></div>
                        <div class="panel-body"><textarea v-model="productLinksText" class="font-mono text-xs" /></div>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="panel">
                    <div class="panel-header">
                        <h3 class="text-sm font-bold text-zinc-950">Schedule</h3>
                        <CalendarClock class="size-4 text-sky-700" />
                    </div>
                    <form class="panel-body space-y-3" @submit.prevent="schedule">
                        <div>
                            <label>Date/time</label>
                            <input v-model="scheduleForm.scheduled_for" type="datetime-local" />
                            <p v-if="scheduledStoreTime" class="mt-1 text-xs text-zinc-500">Currently scheduled for {{ scheduledStoreTime }} — {{ storeTimezone }} time.</p>
                            <p v-if="scheduleForm.errors.scheduled_for" class="mt-1 text-xs text-rose-700">{{ scheduleForm.errors.scheduled_for }}</p>
                        </div>
                        <div>
                            <label>Timezone</label>
                            <input v-model="scheduleForm.timezone" />
                        </div>
                        <div>
                            <label>Recurrence</label>
                            <input v-model="scheduleForm.recurrence_rule" placeholder="Mon,Thu weekly" />
                        </div>
                        <button class="btn btn-primary w-full">Schedule</button>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Scores</h3></div>
                    <div class="panel-body grid grid-cols-2 gap-3">
                        <div class="rounded-md bg-zinc-100 p-3">
                            <div class="text-xs font-semibold text-zinc-500">SEO</div>
                            <div class="mt-1 text-2xl font-bold text-zinc-950">{{ seoScore ?? '-' }}</div>
                        </div>
                        <div class="rounded-md bg-zinc-100 p-3">
                            <div class="text-xs font-semibold text-zinc-500">Readability</div>
                            <div class="mt-1 text-2xl font-bold text-zinc-950">{{ readabilityScore ?? '-' }}</div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Comments</h3></div>
                    <div class="panel-body space-y-3">
                        <form class="space-y-2" @submit.prevent="addComment">
                            <textarea v-model="commentForm.body" class="min-h-20" />
                            <button class="btn btn-secondary w-full">Add comment</button>
                        </form>
                        <div v-for="comment in props.blog.comments" :key="comment.id" class="rounded-md border border-zinc-200 p-3">
                            <div class="text-xs font-semibold text-zinc-500">{{ comment.user?.name ?? 'User' }}</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-zinc-800">{{ comment.body }}</div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header"><h3 class="text-sm font-bold text-zinc-950">Revisions</h3></div>
                    <div class="panel-body space-y-2">
                        <div v-for="revision in props.blog.revisions" :key="revision.id" class="rounded-md border border-zinc-200 p-3 text-sm">
                            <div class="font-semibold text-zinc-950">Version {{ revision.version }}</div>
                            <div class="text-xs text-zinc-500">{{ revision.change_summary }} · {{ revision.user?.name ?? 'System' }}</div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
