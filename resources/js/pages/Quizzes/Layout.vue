<script setup lang="ts">
// Shared layout for Quizzes section (authenticated area)
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
        <a href="/dashboard" class="rounded-full px-4 py-2 ring-1 ring-white/15 transition hover:bg-white/10">Dashboard</a>
        <form method="POST" action="/logout">
          <input type="hidden" name="_token" />
          <button type="submit" class="rounded-full bg-white/10 px-4 py-2 ring-1 ring-white/20 transition hover:bg-white/15">Se déconnecter</button>
        </form>
      </nav>
    </header>

    <!-- Subnav -->
    <div class="border-b border-white/10 bg-white/[0.03]">
      <div class="mx-auto flex max-w-7xl items-center gap-4 px-6">
        <a href="/quizzes" class="inline-flex items-center border-b-2 border-transparent px-1.5 py-3 text-sm text-white/80 hover:text-white" :class="{ 'border-white text-white': $page.url.startsWith('/quizzes') && !$page.url.endsWith('/create') }">Mes quizzes</a>
        <a href="/quizzes/create" class="inline-flex items-center border-b-2 border-transparent px-1.5 py-3 text-sm text-white/80 hover:text-white" :class="{ 'border-white text-white': $page.url.endsWith('/create') }">Créer un quiz</a>
      </div>
    </div>

    <!-- Page content -->
    <main>
      <slot />
    </main>
  </div>
</template>
