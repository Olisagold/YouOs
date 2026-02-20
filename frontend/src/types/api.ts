export type DoctrineGoal = {
  rank: number
  goal: string
}

export type DoctrineHabit = {
  habit: string
  trigger: string
}

export type DoctrineWeeklyTarget = {
  target: string
  metric: string
  current: number
  goal: number
}

export type DoctrineRecord = {
  id: number
  user_id: number
  goals_json: DoctrineGoal[]
  rules_json: string[]
  habits_json: DoctrineHabit[]
  weekly_targets_json: DoctrineWeeklyTarget[]
  created_at: string
  updated_at: string
}

export type DoctrinePayload = {
  goals_json: DoctrineGoal[]
  rules_json: string[]
  habits_json: DoctrineHabit[]
  weekly_targets_json: DoctrineWeeklyTarget[]
}

export type DailyCheckinRecord = {
  id: number
  user_id: number
  checkin_date: string
  energy: number
  mood: number
  missions_json: string[]
  notes: string | null
  created_at: string
  updated_at: string
}

export type DailyCheckinPayload = {
  energy: number
  mood: number
  missions_json: string[]
  notes?: string
}

export type DecisionCategory = 'financial' | 'health' | 'work' | 'social' | 'mindset' | 'other'
export type DecisionUrgency = 'low' | 'medium' | 'high'

export type DecisionContext = {
  what: string
  why: string
  when: string
  urgency: DecisionUrgency
  estimated_impact: string
  alternatives?: string[]
}

export type DecisionAiResponse = {
  verdict: 'approve' | 'reject' | 'delay'
  confidence: number
  reasoning: string[]
  risks: string[]
  better_option: string
  next_steps: string[]
}

export type DecisionRecord = {
  id: number
  user_id: number
  category: DecisionCategory
  context_json: DecisionContext
  ai_response_json: DecisionAiResponse | null
  raw_ai_response?: string | null
  final_choice: string | null
  outcome_notes: string | null
  created_at: string
  updated_at: string
}

export type DecisionCreatePayload = {
  category: DecisionCategory
  context_json: DecisionContext
}

export type DecisionCreateResponse = {
  decision: DecisionRecord
  ai_response: DecisionAiResponse
}

export type WeeklyReviewAiResponse = {
  week_summary: string
  compliance_rate: number
  patterns_detected: string[]
  strongest_day: string
  weakest_day: string
  override_analysis: string
  directive: string
  doctrine_alignment_score: number
}

export type WeeklyReviewRecord = {
  id: number
  user_id: number
  week_start: string
  week_end: string
  ai_review_json: WeeklyReviewAiResponse
  created_at: string
  updated_at: string
}

export type DisciplineStreak = {
  current_streak: number
  longest_streak: number
  last_broken_date: string | null
}

export type Paginated<T> = {
  current_page: number
  data: T[]
  first_page_url: string | null
  from: number | null
  last_page: number
  last_page_url: string | null
  links: Array<{
    url: string | null
    label: string
    active: boolean
  }>
  next_page_url: string | null
  path: string
  per_page: number
  prev_page_url: string | null
  to: number | null
  total: number
}
