<script setup lang="ts">
// Simple landing page for the quiz creation site
</script>

<template>
    <div class="min-h-dvh bg-[#0b1220] text-white">
        <!-- Header -->
        <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
            <a href="/" class="inline-flex items-center gap-2 font-semibold tracking-wide">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">🧠</span>
                <span class="text-lg">Quizz Maker</span>
            </a>
            <nav class="flex items-center gap-3 text-sm text-white/80">
                <a href="/quiz" class="rounded-full px-4 py-2 ring-1 ring-white/15 transition hover:bg-white/10">Jouer un quiz</a>
                <a href="/login" class="rounded-full bg-[#2b7cff] px-4 py-2 font-medium ring-1 ring-white/20 transition hover:brightness-110"
                    >Admin</a
                >
            </nav>
        </header>

        <!-- Hero -->
        <main class="mx-auto max-w-7xl px-6 pt-8 pb-16">
            <div class="grid items-center gap-10 md:grid-cols-2">
                <div>
                    <h1 class="text-4xl leading-tight font-bold sm:text-5xl">Créez des quizzes en quelques clics</h1>
                    <p class="mt-4 text-lg leading-relaxed text-white/80">
                        Un outil simple pour créer, organiser et partager vos questionnaires. Commencez tout de suite avec une question de
                        démonstration.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a
                            href="/quiz"
                            class="inline-flex items-center justify-center rounded-xl bg-[#2b7cff] px-5 py-3 font-semibold ring-1 ring-white/15 transition hover:brightness-110"
                        >
                            Démarrer un quiz
                        </a>
                        <a
                            href="/login"
                            class="inline-flex items-center justify-center rounded-xl px-5 py-3 font-semibold ring-1 ring-white/15 transition hover:bg-white/10"
                        >
                            Créer / gérer (Admin)
                        </a>
                    </div>
                    <p class="mt-3 text-xs text-white/60">Astuce: utilisez l'espace Admin pour ajouter vos questions et réponses.</p>
                </div>

                <!-- Simple preview card -->
                <div class="relative">
                    <div class="rounded-2xl bg-[#1f57c7]/95 p-6 shadow-2xl ring-1 ring-white/10">
                        <div class="mb-4 text-sm text-white/80">Aperçu</div>
                        <div class="rounded-xl bg-white/10 p-5 ring-1 ring-white/15">
                            <div class="text-lg font-medium">Quelle est la capitale de la France ?</div>
                            <div class="mt-4 grid gap-3">
                                <div class="rounded-lg bg-white/10 px-4 py-3 ring-1 ring-white/15">A. Paris</div>
                                <div class="rounded-lg bg-white/5 px-4 py-3 ring-1 ring-white/10">B. Lyon</div>
                                <div class="rounded-lg bg-white/5 px-4 py-3 ring-1 ring-white/10">C. Marseille</div>
                                <div class="rounded-lg bg-white/5 px-4 py-3 ring-1 ring-white/10">D. Bordeaux</div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="pointer-events-none absolute -top-4 -right-4 hidden h-16 w-16 items-center justify-center rounded-full bg-emerald-400/20 ring-2 ring-emerald-300/70 md:flex"
                    >
                        ✅
                    </div>
                </div>
            </div>

            <!-- Features -->
            <section class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl bg-white/5 p-5 ring-1 ring-white/10">
                    <div class="text-base font-semibold">Création rapide</div>
                    <p class="mt-2 text-sm text-white/80">Ajoutez des questions et 4 réponses en un clin d'œil.</p>
                </div>
                <div class="rounded-xl bg-white/5 p-5 ring-1 ring-white/10">
                    <div class="text-base font-semibold">Interface simple</div>
                    <p class="mt-2 text-sm text-white/80">Une page claire pour répondre et valider.</p>
                </div>
                <div class="rounded-xl bg-white/5 p-5 ring-1 ring-white/10">
                    <div class="text-base font-semibold">Admin Filament</div>
                    <p class="mt-2 text-sm text-white/80">Gérez vos questions et réponses depuis /admin.</p>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="mx-auto max-w-7xl px-6 py-8 text-center text-xs text-white/60">
            © {{ new Date().getFullYear() }} Quizz Maker — Fait avec Laravel, Inertia, Vue & Tailwind.
        </footer>
    </div>
</template>
