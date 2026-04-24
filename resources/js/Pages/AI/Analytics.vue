<template>
  <Head title="AI Analytics" />
  
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg">
                <BarChart3 class="w-6 h-6 text-white" />
              </div>
              AI Analytics Dashboard
            </h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">
              Comprehensive insights into AI performance and ticket classification
            </p>
          </div>
          
          <div class="flex items-center gap-3">
            <button 
              @click="refreshAnalytics"
              :disabled="loading"
              class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50 flex items-center gap-2"
            >
              <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
              Refresh
            </button>
            
            <Link 
              :href="route('dashboard')"
              class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all flex items-center gap-2"
            >
              <ArrowLeft class="w-4 h-4" />
              Back to Dashboard
            </Link>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p class="text-slate-600 dark:text-slate-400">Loading AI analytics...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6 mb-8">
        <div class="flex items-center gap-3">
          <AlertCircle class="w-6 h-6 text-red-600 dark:text-red-400" />
          <div>
            <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">Error Loading Analytics</h3>
            <p class="text-red-600 dark:text-red-400 mt-1">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Analytics Content -->
      <div v-else-if="analytics" class="space-y-8">
        <!-- AI Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Total Classifications</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">
                  {{ analytics.classification?.total_classifications || 0 }}
                </p>
              </div>
              <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                <Brain class="w-6 h-6 text-blue-600 dark:text-blue-400" />
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Applied Classifications</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">
                  {{ analytics.classification?.applied_classifications || 0 }}
                </p>
              </div>
              <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                <CheckCircle class="w-6 h-6 text-green-600 dark:text-green-400" />
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">High Confidence</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">
                  {{ analytics.classification?.high_confidence_classifications || 0 }}
                </p>
              </div>
              <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                <TrendingUp class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Average Confidence</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">
                  {{ formatPercentage(analytics.classification?.average_confidence || 0) }}
                </p>
              </div>
              <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                <Target class="w-6 h-6 text-purple-600 dark:text-purple-400" />
              </div>
            </div>
          </div>
        </div>

        <!-- Sentiment Analysis -->
        <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
            <Smile class="w-5 h-5" />
            Ticket Sentiment Analysis
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div v-for="sentiment in analytics.sentiment?.distribution || []" 
                 :key="sentiment.type"
                 class="bg-gradient-to-br p-4 rounded-xl transition-transform hover:scale-[1.02] cursor-pointer"
                 :class="sentiment.class"
                 @click="filterBySentiment(sentiment.type)"
            >
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-3xl mb-1">{{ sentiment.emoji }}</p>
                  <p class="text-sm font-medium">{{ sentiment.label }}</p>
                </div>
                <div class="text-right">
                  <p class="text-2xl font-bold">{{ sentiment.count }}</p>
                  <p class="text-xs opacity-80">{{ formatPercentage(sentiment.percentage) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Sentiment Trend -->
          <div v-if="analytics.sentiment?.trend" class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
            <h4 class="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-4 flex items-center gap-2">
              <TrendingUp class="w-4 h-4" />
              Sentiment Trend (Last 7 Days)
            </h4>
            <div class="flex items-end justify-between h-32">
              <div v-for="(day, index) in analytics.sentiment.trend" 
                   :key="index"
                   class="flex flex-col items-center flex-1 px-1"
              >
                <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ day.day }}</div>
                <div 
                  class="w-full max-w-12 rounded-t-lg transition-all duration-300 hover:opacity-80"
                  :class="getSentimentColorClass(day.dominant)"
                  :style="{ height: (day.total * 3) + 'px' }"
                  :title="`${day.total} tickets - ${getSentimentEmoji(day.dominant)} ${getSentimentLabel(day.dominant)}`"
                ></div>
                <div class="mt-1 text-lg">{{ getSentimentEmoji(day.dominant) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Classification Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Classification Accuracy -->
          <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
              <Target class="w-5 h-5" />
              Classifications Accuracy
            </h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600 dark:text-slate-400">Overall Accuracy</span>
                <span class="text-lg font-semibold text-slate-900 dark:text-white">
                  {{ formatPercentage(analytics.classification?.classification_accuracy || 0) }}
                </span>
              </div>
              <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                <div 
                  class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-300"
                  :style="{ width: (analytics.classification?.classification_accuracy || 0) * 100 + '%' }"
                ></div>
              </div>
            </div>
          </div>

          <!-- AI Generated vs Manual -->
          <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
              <Brain class="w-5 h-5" />
              AI vs Manual Classifications
            </h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600 dark:text-slate-400">AI Generated</span>
                <span class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                  {{ analytics.classification?.ai_generated_classifications || 0 }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600 dark:text-slate-400">Manual</span>
                <span class="text-lg font-semibold text-slate-600 dark:text-slate-400">
                  {{ (analytics.classification?.total_classifications || 0) - (analytics.classification?.ai_generated_classifications || 0) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity with Sentiment -->
     <!-- Recent Activity with Sentiment -->
<div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
  <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
    <Clock class="w-5 h-5" />
    Recent Classification Activity
  </h3>
  
  <div v-if="analytics.classification.recent_activity.length">
    <Link
      v-for="activity in analytics.classification.recent_activity"
      :key="activity.id"
      :href="route('tickets.show', activity.ticket.uid)"
      class="flex items-center justify-between p-4 rounded-lg cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition"
    >
      <div class="flex items-center gap-4">
        <div class="flex flex-col items-center">
          <div class="p-2 rounded-lg mb-1" :class="getSentimentBgClass(activity.sentiment)">
            <span class="text-xl">{{ getSentimentEmoji(activity.sentiment) }}</span>
          </div>
          <span class="text-xs px-2 py-1 rounded-full" :class="getSentimentTextClass(activity.sentiment)">
            {{ getSentimentLabel(activity.sentiment) }}
          </span>
        </div>
        <div>
          <p class="font-medium text-slate-900 dark:text-white">
            Ticket #{{ activity.ticket?.uid || 'N/A' }}
          </p>
          <p class="text-sm text-slate-600 dark:text-slate-400">
            {{ activity.priority?.name || 'Unknown' }} • {{ activity.category?.name || 'Unknown' }}
          </p>
        </div>
      </div>
      <div class="text-right">
        <div class="flex items-center justify-end gap-2 mb-2">
          <div class="text-sm font-medium text-slate-900 dark:text-white">
            {{ formatPercentage(activity.confidence_score) }}
          </div>
          <div class="w-16 bg-slate-200 dark:bg-slate-700 rounded-full h-2">
            <div 
              class="bg-gradient-to-r from-green-500 to-blue-500 h-2 rounded-full"
              :style="{ width: (activity.confidence_score * 100) + '%' }"
            ></div>
          </div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ formatDate(activity.created_at) }}
        </p>
      </div>
    </Link>
  </div>

  <div v-else class="text-center py-8 text-slate-500 dark:text-slate-400">
    <Brain class="w-12 h-12 mx-auto mb-4 text-slate-300 dark:text-slate-600" />
    <p>No recent classification activity</p>
  </div>
</div>


        <!-- Predictive Analytics with Sentiment Impact -->
        <div v-if="analytics.predictions" class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
            <TrendingUp class="w-5 h-5" />
            Predictive Analytics & Sentiment Impact
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 class="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-4">Performance Metrics</h4>
              <div class="space-y-4">
                <div>
                  <div class="flex justify-between mb-1">
                    <span class="text-sm text-slate-600 dark:text-slate-400">Predicted Ticket Volume</span>
                    <span class="text-sm font-semibold">{{ analytics.predictions?.ticket_volume?.predicted || 0 }}</span>
                  </div>
                  <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full" style="width: 85%"></div>
                  </div>
                </div>
                <div>
                  <div class="flex justify-between mb-1">
                    <span class="text-sm text-slate-600 dark:text-slate-400">SLA Compliance</span>
                    <span class="text-sm font-semibold">{{ formatPercentage(analytics.predictions?.sla_compliance?.predicted || 0) }}</span>
                  </div>
                  <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 h-2 rounded-full" style="width: 78%"></div>
                  </div>
                </div>
              </div>
            </div>
            
            <div>
              <h4 class="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-4">Sentiment Impact</h4>
              <div class="space-y-3">
                <div v-for="impact in analytics.sentiment?.impact || []" 
                     :key="impact.sentiment"
                     class="flex items-center justify-between"
                >
                  <div class="flex items-center gap-2">
                    <span class="text-lg">{{ getSentimentEmoji(impact.sentiment) }}</span>
                    <span class="text-sm">{{ getSentimentLabel(impact.sentiment) }}</span>
                  </div>
                  <div class="text-right">
                    <span class="text-sm font-semibold" :class="impact.impact > 0 ? 'text-green-600' : impact.impact < 0 ? 'text-red-600' : 'text-slate-600'">
                      {{ impact.impact > 0 ? '+' : '' }}{{ (impact.impact * 100).toFixed(1) }}%
                    </span>
                    <p class="text-xs text-slate-500">Resolution Time</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


<script>
import { ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import {
  BarChart3,
  RefreshCw,
  ArrowLeft,
  AlertCircle,
  Brain,
  CheckCircle,
  TrendingUp,
  Target,
  Clock,
  Smile
} from 'lucide-vue-next'
import moment from 'moment'

export default {
  name: 'AIAnalytics',
  layout: Layout,
  components: {
    Head,
    Link,
    BarChart3,
    RefreshCw,
    ArrowLeft,
    AlertCircle,
    Brain,
    CheckCircle,
    TrendingUp,
    Target,
    Clock,
    Smile
  },
  setup() {
    const analytics = ref(null)
    const loading = ref(true)
    const error = ref(null)

    // Sentiment configuration
    const sentimentConfig = {
      angry: {
        emoji: '😠',
        label: 'Angry',
        bgColor: 'from-red-500 to-rose-600',
        textColor: 'text-red-700 dark:text-red-400',
        bgClass: 'bg-gradient-to-br from-red-500 to-rose-600',
        lightBg: 'bg-red-100 dark:bg-red-900/30'
      },
      frustrated: {
        emoji: '😤',
        label: 'Frustrated',
        bgColor: 'from-orange-500 to-amber-600',
        textColor: 'text-orange-700 dark:text-orange-400',
        bgClass: 'bg-gradient-to-br from-orange-500 to-amber-600',
        lightBg: 'bg-orange-100 dark:bg-orange-900/30'
      },
      sad: {
        emoji: '😔',
        label: 'Sad',
        bgColor: 'from-blue-500 to-indigo-600',
        textColor: 'text-blue-700 dark:text-blue-400',
        bgClass: 'bg-gradient-to-br from-blue-500 to-indigo-600',
        lightBg: 'bg-blue-100 dark:bg-blue-900/30'
      },
      neutral: {
        emoji: '🤝',
        label: 'Neutral',
        bgColor: 'from-slate-500 to-gray-600',
        textColor: 'text-slate-700 dark:text-slate-400',
        bgClass: 'bg-gradient-to-br from-slate-500 to-gray-600',
        lightBg: 'bg-slate-100 dark:bg-slate-900/30'
      },
      happy: {
        emoji: '😊',
        label: 'Happy',
        bgColor: 'from-green-500 to-emerald-600',
        textColor: 'text-green-700 dark:text-green-400',
        bgClass: 'bg-gradient-to-br from-green-500 to-emerald-600',
        lightBg: 'bg-green-100 dark:bg-green-900/30'
      },
      satisfied: {
        emoji: '👍',
        label: 'Satisfied',
        bgColor: 'from-emerald-500 to-teal-600',
        textColor: 'text-emerald-700 dark:text-emerald-400',
        bgClass: 'bg-gradient-to-br from-emerald-500 to-teal-600',
        lightBg: 'bg-emerald-100 dark:bg-emerald-900/30'
      },
      excited: {
        emoji: '🎉',
        label: 'Excited',
        bgColor: 'from-purple-500 to-pink-600',
        textColor: 'text-purple-700 dark:text-purple-400',
        bgClass: 'bg-gradient-to-br from-purple-500 to-pink-600',
        lightBg: 'bg-purple-100 dark:bg-purple-900/30'
      }
    }

    // Helper function to strip HTML tags
    const stripHtmlTags = (html) => {
      if (!html) return ''
      
      // Simple regex to remove HTML tags
      return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
    }

    // UPDATED - Better balanced sentiment detection
    const analyzeTextForSentiment = (text, ticket = {}) => {
      text = text.toLowerCase().trim()
      
      if (!text || text.length < 5) {
        return 'neutral'
      }
      
    //  console.log(`   Analyzing: "${text.substring(0, 80)}..."`)
      
      // 1. FIRST check for VERY STRONG negative words
      const veryStrongNegative = [
        'furious', 'rage', 'pissed', 'hate', 'terrible', 'awful', 'disgusting'
      ]
      
      for (const word of veryStrongNegative) {
        if (text.includes(word)) {
        //   console.log(`   Found VERY strong negative word: "${word}" → angry`)
          return 'angry'
        }
      }
      
      // 2. Check for strong negative words
      const strongNegativeWords = [
         'crash', 'angry'
      ]
      
      for (const word of strongNegativeWords) {
        if (text.includes(word)) {
        //   console.log(`   Found strong negative word: "${word}" → frustrated`)
          return 'frustrated'
        }
      }
      
      // 3. Check for technical issues
      const technicalIssueWords = [
         'malfunction', 'error'
      ]
      
      // For technical issues, check if it's in the TITLE (more important)
      const title = ticket.title || ''
      const isInTitle = technicalIssueWords.some(word => 
        title.toLowerCase().includes(word)
      )
      
      for (const word of technicalIssueWords) {
        if (text.includes(word)) {
          if (isInTitle) {
            // console.log(`   Technical issue "${word}" in TITLE → frustrated`)
            return 'frustrated'
          } else {
            // If it's in description but not title, might be less urgent
            // console.log(`   Technical issue "${word}" in description only → checking context`)
            // Check word count to see if it's substantial
            const wordCount = text.split(/\s+/).length
            if (wordCount > 20) {
            //   console.log(`   Substantial content (${wordCount} words) → frustrated`)
              return 'frustrated'
            }
          }
        }
      }
      
      // 4. Check for complaint words
      const complaintWords = ['frustrated', 'annoyed', 'irritated']
      
      for (const word of complaintWords) {
        if (text.includes(word)) {
          // "Complaint InProcess" might be just a status
          if (word === 'complaint' && text.includes('inprocess')) {
            // console.log('   "Complaint InProcess" (likely status) → neutral')
            return 'neutral'
          }
        //   console.log(`   Found complaint word: "${word}" → frustrated`)
          return 'frustrated'
        }
      }
      
      // 5. SPECIAL CASES that should be neutral
      if (text.includes('notification forward') || 
          text.includes('forward : [pid') ||
          text.includes('re :') || // Common in email subjects
          text.includes('fyi') ||
          text.includes('information') ||
          text.includes('update')) {
        // console.log('   Notification/Forward/Update → neutral')
        return 'neutral'
      }
      
      // 6. Check for positive sentiments
      const positiveWords = {
        happy: ['happy', 'pleased', 'delighted', 'glad', 'thankful'],
        satisfied: ['satisfied', 'resolved', 'fixed', 'solved', 'thanks', 'thank you'],
        excited: ['excited', 'thrilled', 'amazing', 'wow', 'awesome']
      }
      
      for (const [sentiment, keywords] of Object.entries(positiveWords)) {
        for (const keyword of keywords) {
          if (text.includes(keyword)) {
            // console.log(`   Found positive word: "${keyword}" → ${sentiment}`)
            return sentiment
          }
        }
      }
      
      // 7. Fallback based on priority
      const priority = ticket.priority || ''
      if (['critical', 'high'].includes(priority.toLowerCase())) {
        // Check if it's actually about an issue
        const issueIndicators = ['frustrated']
        if (issueIndicators.some(indicator => text.includes(indicator))) {
        //   console.log(`   High priority with issue indicators → frustrated`)
          return 'frustrated'
        }
      }
      
      // 8. Default to neutral
    //   console.log('   No strong sentiment detected → neutral')
      return 'neutral'
    }

    // SINGLE getTicketSentiment function (no duplicate!)
    const getTicketSentiment = (ticket) => {
      if (!ticket) return 'neutral'
      
      // Handle case where ticket might be a string or minimal object
      if (typeof ticket === 'string') {
        const text = ticket.toLowerCase()
        return analyzeTextForSentiment(text)
      }
      
      // Get title and strip HTML from description
      const title = ticket.title || ticket.subject || ticket.name || ''
      const rawDescription = ticket.description || ticket.content || ticket.body || ''
      
      // STRIP HTML TAGS from description!
      const description = stripHtmlTags(rawDescription)
      
      // Debug what we're analyzing
    //   console.log(`   Analyzing ticket: "${title.substring(0, 30)}..."`)
    //   console.log(`   Raw description length: ${rawDescription.length}`)
    //   console.log(`   Clean description length: ${description.length}`)
    //   console.log(`   Clean description preview: "${description.substring(0, 50)}..."`)
      
      // Combine title + cleaned description
      const combinedText = (title + ' ' + description).toLowerCase().trim()
      
      if (!combinedText) {
        // console.log('   No text to analyze → neutral')
        return 'neutral'
      }
      
    //   console.log(`   Combined text: "${combinedText.substring(0, 100)}..."`)
      
      return analyzeTextForSentiment(combinedText, ticket)
    }

    // Fixed sentiment calculation
    const calculateSentimentDistribution = (tickets) => {
      if (!tickets || !tickets.length) return []
      
      const sentimentCounts = {}
      const totalTickets = tickets.length
      
      // Initialize counts
      Object.keys(sentimentConfig).forEach(sentiment => {
        sentimentCounts[sentiment] = 0
      })
      
      // Analyze each ticket
      tickets.forEach(ticket => {
        const sentiment = getTicketSentiment(ticket)
        sentimentCounts[sentiment]++
      })
      
      // Convert to distribution format
      return Object.entries(sentimentCounts)
        .filter(([_, count]) => count > 0)
        .map(([type, count]) => ({
          type,
          count,
          percentage: count / totalTickets,
          label: sentimentConfig[type]?.label || type.charAt(0).toUpperCase() + type.slice(1),
          emoji: sentimentConfig[type]?.emoji || '🤝',
          class: sentimentConfig[type]?.bgClass || 'bg-gradient-to-br from-slate-500 to-gray-600 text-white'
        }))
        .sort((a, b) => b.count - a.count)
    }

    const calculateSentimentTrend = (tickets) => {
      if (!tickets || !tickets.length) return []
      
      // group tickets by day (last 7 days)
      const last7Days = Array.from({ length: 7 }, (_, i) => {
        const date = new Date()
        date.setDate(date.getDate() - i)
        return date.toISOString().split('T')[0]
      }).reverse()
      
      return last7Days.map(dateStr => {
        const dayTickets = tickets.filter(ticket => {
          const ticketDate = new Date(ticket.created_at).toISOString().split('T')[0]
          return ticketDate === dateStr
        })
        
        if (dayTickets.length === 0) {
          return {
            day: moment(dateStr).format('ddd'),
            total: 0,
            dominant: 'neutral'
          }
        }
        
        // calculate dominant sentiment for the day
        const sentimentScores = {}
        dayTickets.forEach(ticket => {
          const sentiment = getTicketSentiment(ticket)
          sentimentScores[sentiment] = (sentimentScores[sentiment] || 0) + 1
        })
        
        const dominantSentiment = Object.entries(sentimentScores)
          .sort((a, b) => b[1] - a[1])[0]?.[0] || 'neutral'
        
        return {
          day: moment(dateStr).format('ddd'),
          total: dayTickets.length,
          dominant: dominantSentiment
        }
      })
    }

    const calculateSentimentImpact = (tickets) => {
      if (!tickets || !tickets.length) return []
      
      // calculate average resolution time by sentiment
      const sentimentResolutionData = {}
      
      tickets.forEach(ticket => {
        if (!ticket.created_at || !ticket.resolved_at) return
        
        const created = new Date(ticket.created_at)
        const resolved = new Date(ticket.resolved_at)
        const resolutionHours = (resolved - created) / (1000 * 60 * 60)
        
        // detect sentiment for this ticket
        const sentiment = getTicketSentiment(ticket)
        
        if (!sentimentResolutionData[sentiment]) {
          sentimentResolutionData[sentiment] = {
            totalHours: 0,
            count: 0
          }
        }
        
        sentimentResolutionData[sentiment].totalHours += resolutionHours
        sentimentResolutionData[sentiment].count++
      })
      
      // calculate overall average
      const allResolvedTickets = tickets.filter(t => t.resolved_at)
      if (allResolvedTickets.length === 0) return []
      
      const overallAvg = allResolvedTickets.reduce((sum, ticket) => {
        const created = new Date(ticket.created_at)
        const resolved = new Date(ticket.resolved_at)
        return sum + ((resolved - created) / (1000 * 60 * 60))
      }, 0) / allResolvedTickets.length
      
      // calculate impact percentage
      return Object.entries(sentimentResolutionData)
        .map(([sentiment, data]) => {
          const avgForSentiment = data.totalHours / data.count
          const impact = (overallAvg - avgForSentiment) / overallAvg // Positive means faster resolution
          return {
            sentiment,
            impact: -impact // Invert so negative impact means slower resolution
          }
        })
        .sort((a, b) => a.impact - b.impact) // Sort by impact (most negative first)
    }

    // Debug function
    const debugSentiments = () => {
      if (!analytics.value?.classification?.recent_activity) {
        // console.log('No recent activity found')
        return
      }
      
    //   console.log('=== DEBUG SENTIMENT ANALYSIS ===')
    //   console.log('Total activities:', analytics.value.classification.recent_activity.length)
      
      analytics.value.classification.recent_activity.forEach((activity, index) => {
        // console.log(`\n--- Activity ${index + 1} ---`)
        
        // Get the text content
        const title = activity.ticket?.title || activity.title || 'No title'
        const description = activity.ticket?.description || activity.description || ''
        const text = (title + ' ' + description).toLowerCase()
        
        const sentiment = activity.sentiment || getTicketSentiment(activity.ticket || activity)
        
        // console.log(`Ticket #${activity.ticket?.uid || activity.ticket_id || 'N/A'}:`, {
        //   title: title,
        //   description: description.substring(0, 50) + (description.length > 50 ? '...' : ''),
        //   fullText: text.substring(0, 150) + (text.length > 150 ? '...' : ''),
        //   sentiment,
        //   containsDown: text.includes('down'),
        //   containsBroken: text.includes('broken'),
        //   containsIssue: text.includes('issue'),
        //   containsProblem: text.includes('problem'),
        //   priority: activity.priority?.name || activity.ticket?.priority,
        //   status: activity.ticket?.status
        // })
        
        // Specifically check for "down"
        if (text.includes('down')) {
        //   console.log('⚠️  CONTAINS "down" - Should be frustrated/angry!')
        //   console.log('Context around "down":', text.substring(text.indexOf('down') - 20, text.indexOf('down') + 30))
        }
      })
    }

    // Helper functions for template
    const getSentimentEmoji = (type) => {
      return sentimentConfig[type]?.emoji || '🤝'
    }

    const getSentimentLabel = (type) => {
      return sentimentConfig[type]?.label || 'Neutral'
    }

    const getSentimentColorClass = (type) => {
      return sentimentConfig[type]?.bgColor || 'bg-slate-500'
    }

    const getSentimentBgClass = (type) => {
      return sentimentConfig[type]?.lightBg || 'bg-slate-100 dark:bg-slate-900/30'
    }

    const getSentimentTextClass = (type) => {
      return sentimentConfig[type]?.textColor || 'text-slate-700 dark:text-slate-400'
    }

    // Main data loading function
    const loadAnalytics = async () => {
      loading.value = true
      error.value = null
      
      try {
        const response = await fetch('/dashboard/ai/analytics', {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        })

        const result = await response.json()
        
        if (result.success) {
          // CRITICAL DEBUG - Show everything
        //   console.log('🚨 === RAW API DATA CHECK === 🚨')
          const recentActivities = result.data.classification?.recent_activity || []
        //   console.log('Total activities from API:', recentActivities.length)
          
          // Map activities to tickets
          const recentTickets = recentActivities.map(activity => {
            // Try different possible locations for ticket data
            let ticketData = activity.ticket || activity
            
            // Get ALL possible fields for debugging
            const title = ticketData.title || ticketData.subject || ticketData.name || 'Untitled Ticket'
            const description = ticketData.description || ticketData.content || ticketData.body || ticketData.details || ''
            
            // Create ticket object
            const ticket = {
              title: title,
              description: description,
              uid: ticketData.uid || ticketData.id || ticketData.ticket_id || 'N/A',
              priority: activity.priority?.name || ticketData.priority || 'unknown',
              category: activity.category?.name || ticketData.category || 'unknown',
              status: ticketData.status || activity.status || 'unknown',
              created_at: activity.created_at || ticketData.created_at || new Date().toISOString(),
              resolved_at: ticketData.resolved_at || ticketData.closed_at || null,
            }
            
            // Calculate sentiment
            const calculatedSentiment = getTicketSentiment({
              title: title,
              description: description,
              priority: ticket.priority
            })
            
            // console.log(`Ticket: "${title.substring(0, 30)}..." → ${calculatedSentiment}`)
            
            return {
              ...ticket,
              sentiment: calculatedSentiment
            }
          })
          
        //   console.log('=== FINAL TICKETS FOR DISTRIBUTION ===')
        //   console.log('Total tickets:', recentTickets.length)
          
          // Count sentiments manually to verify
          const manualCounts = {}
          recentTickets.forEach(ticket => {
            manualCounts[ticket.sentiment] = (manualCounts[ticket.sentiment] || 0) + 1
          })
        //   console.log('Manual sentiment counts:', manualCounts)
          
          // Calculate dynamic sentiment analytics
          const sentimentDistribution = calculateSentimentDistribution(recentTickets)
        //   console.log('Distribution result:', sentimentDistribution)
          
          const sentimentTrend = calculateSentimentTrend(recentTickets)
          const sentimentImpact = calculateSentimentImpact(recentTickets)
          
          // Add sentiment to recent activity items for display
          
          // Add sentiment to recent activity items for display
const recentActivityWithSentiment = recentActivities
  .map(activity => {
    let ticketData = activity.ticket || activity
    
    // Get ticket text directly from activity
    const title = ticketData.title || ticketData.subject || ticketData.name || 'Untitled Ticket'
    const description = ticketData.description || ticketData.content || ticketData.body || ''
    
    // Calculate sentiment directly for this activity
    const calculatedSentiment = getTicketSentiment({
      title: title,
      description: description,
      priority: activity.priority?.name || ticketData.priority || 'unknown',
      category: activity.category?.name || ticketData.category || 'unknown'
    })
    
    // console.log(`Activity Ticket #${ticketData.uid || ticketData.id || 'N/A'}:`, {
    //   title: title.substring(0, 50),
    //   sentiment: calculatedSentiment
    // })
    
    return {
      ...activity,
      sentiment: calculatedSentiment,
      confidence_score: activity.confidence_score || 0.85, // Default confidence if missing
      ticket: {
        uid: ticketData.uid || ticketData.id || ticketData.ticket_id || 'N/A',
        title: title,
        description: description,
        priority: activity.priority?.name || ticketData.priority || 'unknown',
        category: activity.category?.name || ticketData.category || 'unknown',
        status: ticketData.status || 'unknown'
      },
      // Ensure these fields exist for display
      priority: activity.priority || { name: 'Unknown' },
      category: activity.category || { name: 'Unknown' }
    }
  })
  .sort((a, b) => new Date(b.created_at) - new Date(a.created_at)) // Sort by newest first
  .slice(0, 10) // Get 10 most recent
          
          analytics.value = {
            ...result.data,
            sentiment: {
              distribution: sentimentDistribution,
              trend: sentimentTrend,
              impact: sentimentImpact
            },
            classification: {
              ...result.data.classification,
              recent_activity: recentActivityWithSentiment
            }
          }
          
        //   console.log('=== FINAL ANALYTICS ===')
        //   console.log('Distribution:', analytics.value.sentiment.distribution)
        //   console.log('Recent activity sentiments:', analytics.value.classification.recent_activity.map(a => a.sentiment))
        } else {
          error.value = result.message || 'Failed to load analytics'
        }
      } catch (err) {
        console.error('Failed to load AI analytics:', err)
        error.value = 'Failed to load analytics. Please try again.'
      } finally {
        loading.value = false
      }
    }

    const filterBySentiment = (sentimentType) => {
    //   console.log('Filtering by sentiment:', sentimentType)
      // filtering logic
    }

    const refreshAnalytics = () => {
      loadAnalytics()
    }

    const formatPercentage = (value) => {
      return (value * 100).toFixed(1) + '%'
    }

    const formatDate = (date) => {
      return moment(date).fromNow()
    }

    onMounted(async () => {
      await loadAnalytics()
      debugSentiments()
    })

    return {
      analytics,
      loading,
      error,
      refreshAnalytics,
      formatPercentage,
      formatDate,
      getSentimentEmoji,
      getSentimentLabel,
      getSentimentColorClass,
      getSentimentBgClass,
      getSentimentTextClass,
      filterBySentiment
    }
  }
}
</script>

<style scoped>
/* Smooth transitions */
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}

/* Hover effects */
.hover-scale:hover {
  transform: scale(1.02);
}
</style>
