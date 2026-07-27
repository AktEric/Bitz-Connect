<?php

namespace App\Enums;

/**
 * Centralized catalog of gated features (entitlements).
 *
 * This enum is the single vocabulary for "can this subject do X?" across the
 * whole system. UI and backend both resolve permissions against these keys via
 * the EntitlementService — never by inspecting the plan string directly.
 *
 * Which features each plan grants is declared in config/plans.php.
 * Features marked "(prepared)" are wired through the entitlement layer now so
 * that the eventual feature only needs its own implementation, not new plumbing.
 */
enum Feature: string
{
    // ── Creator / Consultor ───────────────────────────────────
    case PublishVideos       = 'publish_videos';
    case CreateCourses       = 'create_courses';        // (prepared)
    case SellMentorships     = 'sell_mentorships';      // (prepared)
    case CreatorPage         = 'creator_page';
    case ContentAnalytics    = 'content_analytics';
    case ViewStats           = 'view_stats';
    case RecommendationBoost = 'recommendation_boost';  // (prepared)

    // ── Company / Empresa ─────────────────────────────────────
    case PremiumCompanyProfile = 'premium_company_profile';
    case CompanyDashboard      = 'company_dashboard';
    case CompanyAnalytics      = 'company_analytics';
    case JobVisibilityBoost    = 'job_visibility_boost';
    case AdvancedRecruitment   = 'advanced_recruitment';
    case CandidateSearch       = 'candidate_search';
    case ProfessionalFilters   = 'professional_filters';
    case UnlimitedInvites      = 'unlimited_invites';    // (prepared)
    case FeaturedCompanyPage   = 'featured_company_page';

    // ── Shared ────────────────────────────────────────────────
    case ProBadge        = 'pro_badge';
    case SearchHighlight = 'search_highlight';
    case PublishStories  = 'publish_stories';
}
