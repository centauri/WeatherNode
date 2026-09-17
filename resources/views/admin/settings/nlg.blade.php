@extends('layouts.admin')

@section('title', __('NLG / Text Generation'))

@section('content')
<div class="w-full">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('NLG / Text Generation') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('NLG / Text Generation') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Configure Natural Language Generation for forecast text') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @php
        $providers = config('nlg.providers', []);
        $availableLocales = config('localization.locales', []);
        $currentProvider = \App\Models\Setting::getValue('nlg.provider', 'openai');
        $hasApiKey = (bool) \App\Models\Setting::getValue('nlg.api_key');
        $currentModelValue = (string) \App\Models\Setting::getValue('nlg.model', '');
        $selectedAiLocales = \App\Models\Setting::getValue('nlg.ai_locales', null);
        if (!is_array($selectedAiLocales)) {
            $selectedAiLocales = [\App\Models\Setting::defaultLanguage()];
        }
        $selectedAiDays = (string) \App\Models\Setting::getValue('nlg.ai_days', (string) \App\Services\Nlg\ForecastNlgCacheService::DEFAULT_AI_DAYS);
        if ($selectedAiDays === '') {
            $selectedAiDays = (string) \App\Services\Nlg\ForecastNlgCacheService::DEFAULT_AI_DAYS;
        }
    @endphp

    <form action="{{ route('admin.settings.update', 'nlg') }}" method="POST" class="space-y-6"
          x-data="{
              provider: '{{ $currentProvider }}',
              providers: {{ Js::from($providers) }},
              get preset() { return this.providers[this.provider] || {} },
              get requiresKey() { return this.preset.requires_key !== false },
              get showBaseUrl() { return this.provider === 'compatible' || this.provider === 'ollama' },
              testing: false,
              testResult: null,
              testSuccess: false,
              modelValue: @js($currentModelValue),
              selectedFetchedModel: '',
              modelOptions: [],
              fetchingModels: false,
              modelFetchResult: null,
              modelFetchSuccess: false,
              resetModelOptions() {
                  this.modelOptions = [];
                  this.selectedFetchedModel = '';
                  this.modelFetchResult = null;
                  this.modelFetchSuccess = false;
              },
              async testConnection() {
                  this.testing = true;
                  this.testResult = null;
                  try {
                      const res = await fetch('{{ route('admin.settings.test-api') }}', {
                          method: 'POST',
                          headers: {
                              'Content-Type': 'application/json',
                              'Accept': 'application/json',
                              'X-CSRF-TOKEN': '{{ csrf_token() }}'
                          },
                          body: JSON.stringify({ service: 'nlg' })
                      });
                      const data = await res.json();
                      this.testSuccess = data.success;
                      this.testResult = data.message;
                  } catch (e) {
                      this.testSuccess = false;
                      this.testResult = 'Request failed: ' + e.message;
                  } finally {
                      this.testing = false;
                  }
              },
              async fetchModels() {
                  this.fetchingModels = true;
                  this.modelFetchResult = null;
                  try {
                      const formData = new FormData(this.$root);
                      const res = await fetch('{{ route('admin.settings.nlg.models') }}', {
                          method: 'POST',
                          headers: {
                              'Content-Type': 'application/json',
                              'Accept': 'application/json',
                              'X-CSRF-TOKEN': '{{ csrf_token() }}'
                          },
                          body: JSON.stringify({
                              provider: this.provider,
                              base_url: formData.get('nlg_base_url') || '',
                              api_key: formData.get('nlg_api_key') || '',
                              model: this.modelValue || '',
                          })
                      });
                      const data = await res.json();
                      this.modelFetchSuccess = !!data.success;
                      this.modelFetchResult = data.message || null;
                      this.modelOptions = Array.isArray(data.models) ? data.models : [];
                      const preferredModel = [
                          this.modelValue,
                          typeof data.selected_model === 'string' ? data.selected_model : '',
                          typeof data.default_model === 'string' ? data.default_model : '',
                      ].find(value => value && this.modelOptions.includes(value)) || this.modelOptions[0] || '';
                      this.selectedFetchedModel = preferredModel;
                      if (!this.modelValue && this.selectedFetchedModel) {
                          this.modelValue = this.selectedFetchedModel;
                      }
                  } catch (e) {
                      this.modelFetchSuccess = false;
                      this.modelFetchResult = 'Request failed: ' + e.message;
                      this.modelOptions = [];
                      this.selectedFetchedModel = '';
                  } finally {
                      this.fetchingModels = false;
                  }
              }
          }">
        @csrf

        <!-- LLM Settings -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('LLM Enhancement (Optional)') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ __('Enable LLM-powered text enhancement to polish the deterministic forecast text. Falls back to deterministic text if LLM fails.') }}
            </p>

            <div class="space-y-5">
                <!-- Enable toggle -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">{{ __('Enable LLM Enhancement') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Use AI to improve forecast text quality') }}</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="nlg_llm_enabled" value="1"
                               {{ \App\Models\Setting::getValue('nlg.llm_enabled', false) ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <!-- Provider -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('LLM Provider') }}
                    </label>
                    <select name="nlg_provider" x-model="provider" @change="resetModelOptions()"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($providers as $key => $preset)
                            <option value="{{ $key }}" {{ $currentProvider === $key ? 'selected' : '' }}>
                                {{ $preset['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="preset.hint"></p>
                </div>

                <!-- API Key -->
                <div x-show="requiresKey" x-transition x-cloak>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('API Key') }}
                    </label>
                    <input type="text" name="nlg_api_key"
                           autocomplete="off"
                           data-lpignore="true"
                           style="-webkit-text-security: disc; text-security: disc;"
                           placeholder="{{ $hasApiKey ? __('(configured — enter new value to change)') : __('Enter API key') }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono">
                    @if($hasApiKey)
                        <p class="mt-1 text-xs text-green-600 dark:text-green-400 flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            {{ __('Configured (leave empty to keep current value)') }}
                        </p>
                    @endif
                </div>

                <!-- Base URL (custom compatible + ollama only) -->
                <div x-show="showBaseUrl" x-transition x-cloak>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Base URL') }}
                    </label>
                    <input type="url" name="nlg_base_url"
                           value="{{ \App\Models\Setting::getValue('nlg.base_url', '') }}"
                           :placeholder="preset.base_url || '{{ __('e.g. https://api.example.com/v1') }}'"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Leave empty to use the default for this provider') }}</p>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <!-- Model -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Model') }}
                        </label>
                        <div class="space-y-3">
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" name="nlg_model" x-model="modelValue"
                                       :placeholder="preset.default_model || '{{ __('Model name') }}'"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono">
                                <button type="button" @click="fetchModels()" :disabled="fetchingModels"
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-wait whitespace-nowrap">
                                    <span x-text="fetchingModels ? '{{ __('Fetching...') }}' : '{{ __('Fetch Models') }}'"></span>
                                </button>
                            </div>

                            <div x-show="modelOptions.length > 0" x-transition x-cloak>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    {{ __('Available Models') }}
                                </label>
                                <select x-model="selectedFetchedModel" @change="modelValue = selectedFetchedModel"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono">
                                    <template x-for="modelOption in modelOptions" :key="modelOption">
                                        <option :value="modelOption" x-text="modelOption"></option>
                                    </template>
                                </select>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Use one of the discovered models when available, or type a custom model manually.') }}</p>

                            <div x-show="modelFetchResult" x-transition x-cloak class="p-3 rounded-lg text-sm"
                                 :class="modelFetchSuccess ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200' : 'bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200'">
                                <span x-text="modelFetchResult"></span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Leave empty to use the default for this provider') }}</p>
                    </div>

                    <!-- Tone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Default Tone') }}
                        </label>
                        <select name="nlg_default_tone"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @php $currentTone = \App\Models\Setting::getValue('nlg.default_tone', 'brief'); @endphp
                            <option value="brief" {{ $currentTone === 'brief' ? 'selected' : '' }}>{{ __('Brief') }}</option>
                            <option value="friendly" {{ $currentTone === 'friendly' ? 'selected' : '' }}>{{ __('Friendly') }}</option>
                            <option value="formal" {{ $currentTone === 'formal' ? 'selected' : '' }}>{{ __('Formal') }}</option>
                        </select>
                    </div>

                    <!-- Reasoning effort -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Reasoning Effort') }}
                        </label>
                        <select name="nlg_reasoning_effort"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @php $currentReasoningEffort = \App\Models\Setting::getValue('nlg.reasoning_effort', ''); @endphp
                            <option value="" {{ $currentReasoningEffort === '' ? 'selected' : '' }}>{{ __('Default — don\'t send (standard chat models)') }}</option>
                            <option value="disabled" {{ $currentReasoningEffort === 'disabled' ? 'selected' : '' }}>{{ __('Disabled — no thinking (Cerebras GLM / gpt-oss)') }}</option>
                            <option value="low" {{ $currentReasoningEffort === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                            <option value="medium" {{ $currentReasoningEffort === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                            <option value="high" {{ $currentReasoningEffort === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Standard chat models (OpenAI gpt-4o-mini, Google, llama, …): leave on Default — they reject this option.') }}</p>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">{{ __('Reasoning models (OpenAI o-series, and some Groq / OpenRouter / Cerebras models such as zai-glm / gpt-oss): rephrasing needs no thinking, so pick Disabled for Cerebras GLM/gpt-oss, or Low elsewhere. "Default" does NOT turn thinking off — it lets the model reason at full effort, which can use up the whole response on internal thinking and return no text.') }}</p>
                    </div>
                </div>

                <!-- AI Days -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('AI Enhancement Days') }}
                    </label>
                    <select name="nlg_ai_days"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @for($aiDayOption = 1; $aiDayOption <= 14; $aiDayOption++)
                            <option value="{{ $aiDayOption }}" {{ $selectedAiDays === (string) $aiDayOption ? 'selected' : '' }}>
                                {{ $aiDayOption === 1 ? __('First forecast day only') : __('First :days forecast days', ['days' => $aiDayOption]) }}
                            </option>
                        @endfor
                        <option value="all" {{ $selectedAiDays === 'all' ? 'selected' : '' }}>{{ __('All forecast days') }}</option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Choose how many forecast days get the separate AI rephrase pass. Lower values preserve tokens; all forecast days uses the most.') }}
                    </p>
                </div>

                <!-- Both temperature scales -->
                <div class="flex items-start justify-between gap-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">{{ __('Polish both temperature scales') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('The forecast paragraph is written once in Celsius and once in Fahrenheit, so readers see their own units. The AI pass rewrites prose, which cannot be converted afterwards, so polishing both means asking the provider twice.') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('Off: only :scale is polished, and readers who switch units get the plain sentence in the right units. On: both are polished, and token use roughly doubles.', ['scale' => \App\Models\Setting::getValue('display.unit_system', 'metric') === 'imperial' ? __('Fahrenheit') : __('Celsius')]) }}
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                        <input type="checkbox" name="nlg_rephrase_both_units" value="1"
                               {{ \App\Services\Nlg\ForecastNlgCacheService::rephrasesBothScales() ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <!-- AI Languages -->
                <div>
                    <div class="flex items-start justify-between gap-4 mb-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('AI Enhancement Languages') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ __('Only these languages use the separate AI rephrase pass. All other languages stay deterministic and cost no extra tokens.') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($availableLocales as $localeKey => $localeMeta)
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 bg-gray-50 dark:bg-gray-900/40">
                                <input type="checkbox"
                                       name="nlg_ai_locales[]"
                                       value="{{ $localeKey }}"
                                       {{ in_array($localeKey, $selectedAiLocales, true) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                <span class="text-sm text-gray-700 dark:text-gray-200">
                                    {{ $localeMeta['label'] ?? $localeKey }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        {{ __('Leave all unchecked to disable scheduled AI enhancement without turning off deterministic NLG.') }}
                    </p>
                </div>

                <!-- Test Connection -->
                <div class="pt-2">
                    <button type="button" @click="testConnection()" :disabled="testing"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-wait">
                        <template x-if="testing">
                            <svg class="animate-spin -ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <template x-if="!testing">
                            <svg class="w-4 h-4 mr-2 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </template>
                        <span x-text="testing ? '{{ __('Testing...') }}' : '{{ __('Test Connection') }}'"></span>
                    </button>

                    <div x-show="testResult" x-transition x-cloak class="mt-3 p-3 rounded-lg text-sm"
                         :class="testSuccess ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200'">
                        <div class="flex items-start">
                            <template x-if="testSuccess">
                                <svg class="w-5 h-5 mr-2 mt-0.5 shrink-0 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </template>
                            <template x-if="!testSuccess">
                                <svg class="w-5 h-5 mr-2 mt-0.5 shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </template>
                            <span x-text="testResult"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thresholds -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Output Thresholds') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ __('Control when precipitation and other events are mentioned in the forecast text.') }}
            </p>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Min Amount to Mention (mm)') }}
                    </label>
                    <input type="number" name="nlg_min_amount" step="0.1" min="0"
                           value="{{ \App\Models\Setting::getValue('nlg.min_amount', 0.1) }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Precipitation below this amount may not be mentioned') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Min Probability to Mention (%)') }}
                    </label>
                    <input type="number" name="nlg_min_prob" step="1" min="0" max="100"
                           value="{{ \App\Models\Setting::getValue('nlg.min_prob', 60) }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Probability threshold when amount is low') }}</p>
                </div>
            </div>
        </div>

        <!-- Provider request limits -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Provider Request Limits') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ __('Match your AI provider plan to avoid hitting its quota. The per-minute limit makes the scheduled pass wait; the hourly and daily limits make it stop early and keep the deterministic text until the quota resets. Leave a field blank or 0 for no limit. Example for Cerebras free: 5 / 150 / 2400.') }}
            </p>

            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Requests per minute') }}
                    </label>
                    <input type="number" name="nlg_limit_rpm" step="1" min="0"
                           value="{{ \App\Models\Setting::getValue('nlg.limits.rpm', '') }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Requests per hour') }}
                    </label>
                    <input type="number" name="nlg_limit_rph" step="1" min="0"
                           value="{{ \App\Models\Setting::getValue('nlg.limits.rph', '') }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Requests per day') }}
                    </label>
                    <input type="number" name="nlg_limit_rpd" step="1" min="0"
                           value="{{ \App\Models\Setting::getValue('nlg.limits.rpd', '') }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('admin.settings.index') }}"
               class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                {{ __('Cancel') }}
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                {{ __('Save Settings') }}
            </button>
        </div>
    </form>
</div>
@endsection
