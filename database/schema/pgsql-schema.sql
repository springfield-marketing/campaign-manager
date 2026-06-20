--
-- PostgreSQL database dump
--

\restrict TskTgW3aH7iDUYpr8jLvvK3r9GMjgymHt7eLfLiVH5Pk8CFe5nhNOipFNpo8hcL

-- Dumped from database version 18.3 (Homebrew)
-- Dumped by pg_dump version 18.3 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: buildings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildings (
    id bigint NOT NULL,
    emirate character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    project_id bigint,
    marketing_area_id bigint,
    official_area_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: buildings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: buildings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildings_id_seq OWNED BY public.buildings.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: campaign_target_locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.campaign_target_locations (
    id bigint NOT NULL,
    campaign_id bigint,
    campaign_type character varying(255),
    emirate character varying(255) NOT NULL,
    marketing_area_id bigint,
    project_id bigint,
    building_id bigint,
    include_projects boolean DEFAULT true NOT NULL,
    include_buildings boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN campaign_target_locations.campaign_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.campaign_target_locations.campaign_type IS 'ivr | whatsapp';


--
-- Name: campaign_target_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.campaign_target_locations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: campaign_target_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.campaign_target_locations_id_seq OWNED BY public.campaign_target_locations.id;


--
-- Name: central_database_exports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.central_database_exports (
    id bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    file_name character varying(255),
    storage_path character varying(255),
    requested_by bigint,
    total_rows bigint DEFAULT '0'::bigint NOT NULL,
    processed_rows bigint DEFAULT '0'::bigint NOT NULL,
    file_size bigint,
    summary json,
    error_message text,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: central_database_exports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.central_database_exports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: central_database_exports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.central_database_exports_id_seq OWNED BY public.central_database_exports.id;


--
-- Name: cleanup_suspicious_contact_names_20260609_backup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cleanup_suspicious_contact_names_20260609_backup (
    id bigint,
    full_name character varying(255),
    reason text,
    backed_up_at timestamp with time zone
);


--
-- Name: cleanup_suspicious_contact_names_20260609_preview; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cleanup_suspicious_contact_names_20260609_preview (
    id bigint,
    reason text,
    current_name text,
    new_name text,
    action text
);


--
-- Name: client_interactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_interactions (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    source character varying(255),
    description text,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT client_interactions_type_check CHECK (((type)::text = ANY ((ARRAY['ivr_campaign'::character varying, 'whatsapp_campaign'::character varying, 'agent_upload'::character varying, 'manual_entry'::character varying, 'import'::character varying, 'note'::character varying, 'phone_call'::character varying])::text[])))
);


--
-- Name: client_phone_numbers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_phone_numbers (
    id bigint NOT NULL,
    client_id bigint,
    raw_phone character varying(255) NOT NULL,
    normalized_phone character varying(255) NOT NULL,
    country_code character varying(255),
    national_number character varying(255),
    detected_country character varying(255),
    is_uae boolean DEFAULT false NOT NULL,
    last_source_name character varying(255),
    last_imported_at timestamp(0) without time zone,
    unsubscribed_at timestamp(0) without time zone,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    label character varying(255),
    is_primary boolean DEFAULT false NOT NULL,
    is_whatsapp boolean DEFAULT false NOT NULL,
    verification_status character varying(255) DEFAULT 'unverified'::character varying NOT NULL,
    priority smallint DEFAULT '100'::smallint NOT NULL,
    is_whatsapp_lead boolean DEFAULT false NOT NULL,
    is_shared_line boolean DEFAULT false NOT NULL,
    shared_line_note character varying(255),
    is_ivr boolean DEFAULT false NOT NULL,
    reentered_while_suppressed_at timestamp(0) without time zone,
    CONSTRAINT client_phone_numbers_format_check CHECK ((((verification_status)::text <> 'unverified'::text) OR (((normalized_phone)::text ~ '^\+?[0-9]{9,15}$'::text) AND ("right"(regexp_replace((normalized_phone)::text, '\D'::text, ''::text, 'g'::text), 9) !~ '0{6,}$'::text) AND ("right"(regexp_replace((normalized_phone)::text, '\D'::text, ''::text, 'g'::text), 9) !~ '(.)\1{5,}'::text) AND ("right"(regexp_replace((normalized_phone)::text, '\D'::text, ''::text, 'g'::text), 9) !~ '(0123456|1234567|2345678|3456789|9876543|8765432|7654321|6543210|01234567|12345678|23456789|98765432|87654321|76543210|012345678|123456789|987654321|876543210)$'::text)))),
    CONSTRAINT client_phone_numbers_verification_status_check CHECK (((verification_status)::text = ANY ((ARRAY['unverified'::character varying, 'verified'::character varying, 'invalid'::character varying])::text[])))
);


--
-- Name: ivr_call_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_call_records (
    id bigint NOT NULL,
    ivr_campaign_id bigint,
    ivr_import_id bigint,
    client_phone_number_id bigint NOT NULL,
    external_call_uuid character varying(255) NOT NULL,
    call_time timestamp(0) without time zone,
    call_direction character varying(255),
    call_status character varying(255),
    customer_status character varying(255),
    agent_status character varying(255),
    total_duration_seconds integer DEFAULT 0 NOT NULL,
    talk_time_seconds integer DEFAULT 0 NOT NULL,
    call_action character varying(255),
    dtmf_extensions json,
    dtmf_outcome character varying(255),
    queue character varying(255),
    disposition character varying(255),
    sub_disposition character varying(255),
    hangup_by character varying(255),
    ivr_id character varying(255),
    credits_deducted numeric(10,2),
    follow_up_date timestamp(0) without time zone,
    raw_payload json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT ivr_call_records_dtmf_outcome_check CHECK (((dtmf_outcome IS NULL) OR ((dtmf_outcome)::text = ANY ((ARRAY['interested'::character varying, 'more_info'::character varying, 'unsubscribe'::character varying, 'no_input'::character varying, 'other'::character varying])::text[]))))
);


--
-- Name: ivr_campaigns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_campaigns (
    id bigint NOT NULL,
    external_campaign_id character varying(255) NOT NULL,
    name character varying(255),
    platform character varying(255),
    state character varying(255),
    total_calls integer DEFAULT 0 NOT NULL,
    answered_calls integer DEFAULT 0 NOT NULL,
    unanswered_calls integer DEFAULT 0 NOT NULL,
    leads_count integer DEFAULT 0 NOT NULL,
    more_info_count integer DEFAULT 0 NOT NULL,
    unsubscribed_count integer DEFAULT 0 NOT NULL,
    credits_used numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    summary json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    audio_file_path character varying(255),
    audio_original_name character varying(255),
    audio_script text,
    ivr_script_id bigint
);


--
-- Name: whatsapp_campaigns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_campaigns (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    total_messages integer DEFAULT 0 NOT NULL,
    delivered_count integer DEFAULT 0 NOT NULL,
    read_count integer DEFAULT 0 NOT NULL,
    failed_count integer DEFAULT 0 NOT NULL,
    unsubscribed_count integer DEFAULT 0 NOT NULL,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    summary json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sent_count integer DEFAULT 0 NOT NULL,
    replied_count integer DEFAULT 0 NOT NULL,
    platform character varying(255)
);


--
-- Name: whatsapp_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_messages (
    id bigint NOT NULL,
    whatsapp_campaign_id bigint NOT NULL,
    whatsapp_import_id bigint NOT NULL,
    client_phone_number_id bigint,
    scheduled_at timestamp(0) without time zone,
    template_name character varying(255),
    delivery_status character varying(255),
    failure_reason text,
    has_quick_replies boolean DEFAULT false NOT NULL,
    quick_reply_1 character varying(255),
    quick_reply_2 character varying(255),
    quick_reply_3 character varying(255),
    clicked boolean DEFAULT false NOT NULL,
    retried boolean DEFAULT false NOT NULL,
    raw_payload json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT whatsapp_messages_delivery_status_check CHECK (((delivery_status IS NULL) OR ((delivery_status)::text = ANY ((ARRAY['SENT'::character varying, 'DELIVERED'::character varying, 'READ'::character varying, 'REPLIED'::character varying, 'FAILED'::character varying, 'STOPPED'::character varying, 'PENDING'::character varying])::text[]))))
);


--
-- Name: client_activity_timeline; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.client_activity_timeline AS
 SELECT ('interaction:'::text || (ci.id)::text) AS id,
    ci.client_id,
    ci.created_at AS activity_at,
    'manual'::character varying AS channel,
    ci.type AS activity_type,
    COALESCE(NULLIF((ci.source)::text, ''::text), 'Manual note'::text) AS title,
    NULL::character varying AS status,
    ci.description AS detail,
    NULL::character varying AS phone_number,
    NULL::character varying AS campaign_name,
    NULL::character varying AS campaign_reference,
    ci.id AS source_id
   FROM public.client_interactions ci
UNION ALL
 SELECT ((('ivr_campaign:'::text || COALESCE((cr.ivr_campaign_id)::text, 'none'::text)) || ':'::text) || (cpn.id)::text) AS id,
    cpn.client_id,
    max(COALESCE(cr.call_time, cr.created_at)) AS activity_at,
    'ivr'::character varying AS channel,
    'ivr_campaign'::character varying AS activity_type,
    COALESCE(NULLIF((ic.name)::text, ''::text), NULLIF((ic.external_campaign_id)::text, ''::text), 'IVR Campaign'::text) AS title,
    string_agg(DISTINCT (cr.call_status)::text, ', '::text ORDER BY (cr.call_status)::text) AS status,
    concat_ws(' | '::text, (((count(*))::text || ' call'::text) ||
        CASE
            WHEN (count(*) = 1) THEN ''::text
            ELSE 's'::text
        END), NULLIF(('Outcomes: '::text || string_agg(DISTINCT (cr.dtmf_outcome)::text, ', '::text ORDER BY (cr.dtmf_outcome)::text) FILTER (WHERE ((cr.dtmf_outcome IS NOT NULL) AND ((cr.dtmf_outcome)::text <> ''::text)))), 'Outcomes: '::text), NULLIF(('Dispositions: '::text || string_agg(DISTINCT (cr.disposition)::text, ', '::text ORDER BY (cr.disposition)::text) FILTER (WHERE ((cr.disposition IS NOT NULL) AND ((cr.disposition)::text <> ''::text)))), 'Dispositions: '::text)) AS detail,
    cpn.normalized_phone AS phone_number,
    ic.name AS campaign_name,
    ic.external_campaign_id AS campaign_reference,
    min(cr.id) AS source_id
   FROM ((public.ivr_call_records cr
     JOIN public.client_phone_numbers cpn ON ((cpn.id = cr.client_phone_number_id)))
     LEFT JOIN public.ivr_campaigns ic ON ((ic.id = cr.ivr_campaign_id)))
  WHERE (cpn.client_id IS NOT NULL)
  GROUP BY cpn.client_id, cpn.id, cpn.normalized_phone, cr.ivr_campaign_id, ic.name, ic.external_campaign_id
UNION ALL
 SELECT ((('whatsapp_campaign:'::text || (wm.whatsapp_campaign_id)::text) || ':'::text) || (cpn.id)::text) AS id,
    cpn.client_id,
    max(COALESCE(wm.scheduled_at, wm.created_at)) AS activity_at,
    'whatsapp'::character varying AS channel,
    'whatsapp_campaign'::character varying AS activity_type,
    COALESCE(NULLIF((wc.name)::text, ''::text), 'WhatsApp Campaign'::text) AS title,
    string_agg(DISTINCT (wm.delivery_status)::text, ', '::text ORDER BY (wm.delivery_status)::text) AS status,
    concat_ws(' | '::text, (((count(*))::text || ' message'::text) ||
        CASE
            WHEN (count(*) = 1) THEN ''::text
            ELSE 's'::text
        END), NULLIF(('Templates: '::text || string_agg(DISTINCT (wm.template_name)::text, ', '::text ORDER BY (wm.template_name)::text) FILTER (WHERE ((wm.template_name IS NOT NULL) AND ((wm.template_name)::text <> ''::text)))), 'Templates: '::text), NULLIF(('Failures: '::text || string_agg(DISTINCT wm.failure_reason, ', '::text ORDER BY wm.failure_reason) FILTER (WHERE ((wm.failure_reason IS NOT NULL) AND (wm.failure_reason <> ''::text)))), 'Failures: '::text),
        CASE
            WHEN bool_or(wm.clicked) THEN 'Clicked'::text
            ELSE NULL::text
        END) AS detail,
    cpn.normalized_phone AS phone_number,
    wc.name AS campaign_name,
    wc.name AS campaign_reference,
    min(wm.id) AS source_id
   FROM ((public.whatsapp_messages wm
     JOIN public.client_phone_numbers cpn ON ((cpn.id = wm.client_phone_number_id)))
     LEFT JOIN public.whatsapp_campaigns wc ON ((wc.id = wm.whatsapp_campaign_id)))
  WHERE (cpn.client_id IS NOT NULL)
  GROUP BY cpn.client_id, cpn.id, cpn.normalized_phone, wm.whatsapp_campaign_id, wc.name;


--
-- Name: client_audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_audit_logs (
    id bigint NOT NULL,
    action character varying(255) NOT NULL,
    client_id bigint NOT NULL,
    target_client_id bigint,
    reason text,
    performed_by character varying(255),
    snapshot json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_audit_logs_id_seq OWNED BY public.client_audit_logs.id;


--
-- Name: client_emails; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_emails (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    email character varying(255) NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_emails_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_emails_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_emails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_emails_id_seq OWNED BY public.client_emails.id;


--
-- Name: client_interactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_interactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_interactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_interactions_id_seq OWNED BY public.client_interactions.id;


--
-- Name: client_phone_numbers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_phone_numbers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_phone_numbers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_phone_numbers_id_seq OWNED BY public.client_phone_numbers.id;


--
-- Name: client_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_sources (
    id bigint NOT NULL,
    client_id bigint,
    client_phone_number_id bigint,
    channel character varying(255) NOT NULL,
    source_type character varying(255) NOT NULL,
    source_name character varying(255),
    source_file_name character varying(255),
    source_reference character varying(255),
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_sources_id_seq OWNED BY public.client_sources.id;


--
-- Name: client_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_tags (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    tag_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_tags_id_seq OWNED BY public.client_tags.id;


--
-- Name: clients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clients (
    id bigint NOT NULL,
    full_name character varying(255),
    nationality character varying(255),
    resident character varying(255),
    gender character varying(255),
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    interest character varying(255),
    country_iso character varying(2),
    emirate character varying(255),
    tier character varying(20),
    wealth_score smallint,
    completeness_score smallint,
    alternate_names jsonb,
    original_source character varying(255),
    notes text,
    is_institution boolean DEFAULT false NOT NULL
);


--
-- Name: clients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clients_id_seq OWNED BY public.clients.id;


--
-- Name: contact_suppressions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contact_suppressions (
    id bigint NOT NULL,
    client_phone_number_id bigint NOT NULL,
    channel character varying(255),
    reason character varying(255) NOT NULL,
    context json,
    suppressed_at timestamp(0) without time zone NOT NULL,
    released_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    platform character varying(255)
);


--
-- Name: contact_suppressions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contact_suppressions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contact_suppressions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contact_suppressions_id_seq OWNED BY public.contact_suppressions.id;


--
-- Name: countries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.countries (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    iso_code character varying(2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: countries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.countries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.countries_id_seq OWNED BY public.countries.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: import_staging; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_staging (
    id bigint NOT NULL,
    batch_id character varying(255) NOT NULL,
    name character varying(255),
    phone character varying(255),
    email character varying(255),
    country_iso character varying(2),
    emirate character varying(255),
    raw_official_area character varying(255),
    raw_marketing_area character varying(255),
    raw_project_name character varying(255),
    raw_building_name character varying(255),
    raw_unit_reference character varying(255),
    official_area_id bigint,
    marketing_area_id bigint,
    project_id bigint,
    building_id bigint,
    relationship_type character varying(255),
    confidence_level character varying(255),
    source character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    status_reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: import_staging_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.import_staging_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: import_staging_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.import_staging_id_seq OWNED BY public.import_staging.id;


--
-- Name: ivr_call_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_call_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_call_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_call_records_id_seq OWNED BY public.ivr_call_records.id;


--
-- Name: ivr_campaigns_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_campaigns_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_campaigns_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_campaigns_id_seq OWNED BY public.ivr_campaigns.id;


--
-- Name: ivr_import_errors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_import_errors (
    id bigint NOT NULL,
    ivr_import_id bigint NOT NULL,
    row_number integer,
    error_type character varying(255),
    error_message text NOT NULL,
    row_payload json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ivr_import_errors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_import_errors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_import_errors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_import_errors_id_seq OWNED BY public.ivr_import_errors.id;


--
-- Name: ivr_imports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_imports (
    id bigint NOT NULL,
    type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    original_file_name character varying(255) NOT NULL,
    stored_file_name character varying(255) NOT NULL,
    storage_path character varying(255) NOT NULL,
    source_name character varying(255),
    uploaded_by bigint,
    total_rows integer DEFAULT 0 NOT NULL,
    processed_rows integer DEFAULT 0 NOT NULL,
    successful_rows integer DEFAULT 0 NOT NULL,
    failed_rows integer DEFAULT 0 NOT NULL,
    duplicate_rows integer DEFAULT 0 NOT NULL,
    error_message text,
    summary json,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    reverted_at timestamp(0) without time zone,
    reverted_by bigint,
    revert_reason text,
    audio_file_path character varying(255),
    audio_original_name character varying(255),
    audio_script text,
    ivr_script_id bigint,
    tag_id bigint,
    CONSTRAINT ivr_imports_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'completed'::character varying, 'completed_with_errors'::character varying, 'failed'::character varying, 'deleting'::character varying, 'deleted'::character varying, 'delete_failed'::character varying, 'reverting'::character varying, 'reverted'::character varying, 'revert_failed'::character varying])::text[]))),
    CONSTRAINT ivr_imports_type_check CHECK (((type)::text = ANY ((ARRAY['raw_contacts'::character varying, 'campaign_results'::character varying, 'unsubscribers'::character varying])::text[])))
);


--
-- Name: ivr_imports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_imports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_imports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_imports_id_seq OWNED BY public.ivr_imports.id;


--
-- Name: ivr_monthly_summaries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_monthly_summaries (
    id bigint NOT NULL,
    year smallint NOT NULL,
    month smallint,
    total_calls integer DEFAULT 0 NOT NULL,
    answered_calls integer DEFAULT 0 NOT NULL,
    missed_calls integer DEFAULT 0 NOT NULL,
    leads integer DEFAULT 0 NOT NULL,
    more_info integer DEFAULT 0 NOT NULL,
    unsubscribed integer DEFAULT 0 NOT NULL,
    minutes_consumed integer DEFAULT 0 NOT NULL,
    computed_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ivr_monthly_summaries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_monthly_summaries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_monthly_summaries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_monthly_summaries_id_seq OWNED BY public.ivr_monthly_summaries.id;


--
-- Name: ivr_phone_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_phone_profiles (
    id bigint NOT NULL,
    client_phone_number_id bigint NOT NULL,
    usage_status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    last_call_outcome character varying(255),
    last_called_at timestamp(0) without time zone,
    cooldown_until timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ivr_phone_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_phone_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_phone_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_phone_profiles_id_seq OWNED BY public.ivr_phone_profiles.id;


--
-- Name: ivr_scripts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_scripts (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    audio_file_path character varying(255),
    audio_original_name character varying(255),
    audio_script text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ivr_scripts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_scripts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_scripts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_scripts_id_seq OWNED BY public.ivr_scripts.id;


--
-- Name: ivr_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ivr_settings (
    id bigint NOT NULL,
    monthly_minutes_quota integer DEFAULT 50000 NOT NULL,
    price_per_minute_under numeric(8,4) DEFAULT 0.37 NOT NULL,
    price_per_minute_over numeric(8,4) DEFAULT 0.4 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    lock_key character varying(255) DEFAULT 'default'::character varying NOT NULL,
    cooldown_answered_days smallint DEFAULT '14'::smallint NOT NULL,
    cooldown_missed_days smallint DEFAULT '1'::smallint NOT NULL
);


--
-- Name: ivr_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ivr_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ivr_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ivr_settings_id_seq OWNED BY public.ivr_settings.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: marketing_area_official_areas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.marketing_area_official_areas (
    id bigint NOT NULL,
    marketing_area_id bigint NOT NULL,
    official_area_id bigint NOT NULL,
    confidence_level character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN marketing_area_official_areas.confidence_level; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.marketing_area_official_areas.confidence_level IS 'high|medium|low';


--
-- Name: marketing_area_official_areas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.marketing_area_official_areas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: marketing_area_official_areas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.marketing_area_official_areas_id_seq OWNED BY public.marketing_area_official_areas.id;


--
-- Name: marketing_areas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.marketing_areas (
    id bigint NOT NULL,
    emirate character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: marketing_areas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.marketing_areas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: marketing_areas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.marketing_areas_id_seq OWNED BY public.marketing_areas.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: official_areas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.official_areas (
    id bigint NOT NULL,
    emirate character varying(255) NOT NULL,
    source_area_id integer,
    area_name_en character varying(255) NOT NULL,
    zone_id smallint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN official_areas.source_area_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.official_areas.source_area_id IS 'DLD area ID or equivalent government ID';


--
-- Name: COLUMN official_areas.zone_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.official_areas.zone_id IS '1=Non-Freehold 2=Freehold';


--
-- Name: official_areas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.official_areas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: official_areas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.official_areas_id_seq OWNED BY public.official_areas.id;


--
-- Name: ownerships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ownerships (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    emirate character varying(255) NOT NULL,
    official_area_id bigint,
    marketing_area_id bigint,
    project_id bigint,
    building_id bigint,
    unit_reference character varying(255),
    relationship_type character varying(255) NOT NULL,
    confidence_level character varying(255),
    last_source_name character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    source_names jsonb,
    first_confirmed_at timestamp(0) without time zone
);


--
-- Name: ownerships_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ownerships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ownerships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ownerships_id_seq OWNED BY public.ownerships.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: place_aliases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.place_aliases (
    id bigint NOT NULL,
    entity_type character varying(255) NOT NULL,
    entity_id bigint NOT NULL,
    alias_name character varying(255) NOT NULL,
    source character varying(255),
    confidence_level character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN place_aliases.confidence_level; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.place_aliases.confidence_level IS 'high|medium|low';


--
-- Name: place_aliases_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.place_aliases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: place_aliases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.place_aliases_id_seq OWNED BY public.place_aliases.id;


--
-- Name: projects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projects (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    dld_project_id integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    emirate character varying(255),
    marketing_area_id bigint,
    official_area_id bigint,
    developer_name character varying(255),
    is_active boolean DEFAULT true NOT NULL
);


--
-- Name: projects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.projects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.projects_id_seq OWNED BY public.projects.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tags (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tags_id_seq OWNED BY public.tags.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: whatsapp_campaigns_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_campaigns_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_campaigns_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_campaigns_id_seq OWNED BY public.whatsapp_campaigns.id;


--
-- Name: whatsapp_export_batch_numbers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_export_batch_numbers (
    whatsapp_export_batch_id bigint NOT NULL,
    client_phone_number_id bigint NOT NULL
);


--
-- Name: whatsapp_export_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_export_batches (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    exported_by bigint,
    record_count integer DEFAULT 0 NOT NULL,
    filters_summary json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: whatsapp_export_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_export_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_export_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_export_batches_id_seq OWNED BY public.whatsapp_export_batches.id;


--
-- Name: whatsapp_import_errors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_import_errors (
    id bigint NOT NULL,
    whatsapp_import_id bigint NOT NULL,
    row_number integer,
    error_type character varying(255),
    error_message text NOT NULL,
    row_payload json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: whatsapp_import_errors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_import_errors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_import_errors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_import_errors_id_seq OWNED BY public.whatsapp_import_errors.id;


--
-- Name: whatsapp_imports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_imports (
    id bigint NOT NULL,
    type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    original_file_name character varying(255) NOT NULL,
    stored_file_name character varying(255),
    storage_path character varying(255),
    uploaded_by bigint,
    total_rows integer DEFAULT 0 NOT NULL,
    processed_rows integer DEFAULT 0 NOT NULL,
    successful_rows integer DEFAULT 0 NOT NULL,
    failed_rows integer DEFAULT 0 NOT NULL,
    duplicate_rows integer DEFAULT 0 NOT NULL,
    error_message text,
    summary json,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    reverted_at timestamp(0) without time zone,
    reverted_by bigint,
    revert_reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    source_name character varying(255),
    column_mapping jsonb,
    lenient_phones boolean DEFAULT false NOT NULL,
    CONSTRAINT whatsapp_imports_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'pending'::character varying, 'processing'::character varying, 'completed'::character varying, 'completed_with_errors'::character varying, 'failed'::character varying, 'deleting'::character varying, 'deleted'::character varying, 'delete_failed'::character varying, 'reverting'::character varying, 'reverted'::character varying, 'revert_failed'::character varying])::text[]))),
    CONSTRAINT whatsapp_imports_type_check CHECK (((type)::text = ANY ((ARRAY['raw_contacts'::character varying, 'campaign_results'::character varying, 'unsubscribers'::character varying])::text[])))
);


--
-- Name: whatsapp_imports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_imports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_imports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_imports_id_seq OWNED BY public.whatsapp_imports.id;


--
-- Name: whatsapp_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_messages_id_seq OWNED BY public.whatsapp_messages.id;


--
-- Name: whatsapp_monthly_summaries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_monthly_summaries (
    id bigint NOT NULL,
    year smallint NOT NULL,
    month smallint,
    total_messages integer DEFAULT 0 NOT NULL,
    delivered_count integer DEFAULT 0 NOT NULL,
    read_count integer DEFAULT 0 NOT NULL,
    failed_count integer DEFAULT 0 NOT NULL,
    computed_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sent_count integer DEFAULT 0 NOT NULL,
    replied_count integer DEFAULT 0 NOT NULL,
    unsubscribed_count integer DEFAULT 0 NOT NULL
);


--
-- Name: whatsapp_monthly_summaries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_monthly_summaries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_monthly_summaries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_monthly_summaries_id_seq OWNED BY public.whatsapp_monthly_summaries.id;


--
-- Name: whatsapp_phone_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_phone_profiles (
    id bigint NOT NULL,
    client_phone_number_id bigint NOT NULL,
    consecutive_hard_fail_count integer DEFAULT 0 CONSTRAINT whatsapp_phone_profiles_consecutive_failed_count_not_null NOT NULL,
    last_message_status character varying(255),
    last_messaged_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    usage_status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    cooldown_until timestamp(0) without time zone,
    last_failure_reason text,
    manually_dead boolean DEFAULT false NOT NULL,
    CONSTRAINT whatsapp_phone_profiles_usage_status_check CHECK (((usage_status)::text = ANY ((ARRAY['active'::character varying, 'cooldown'::character varying, 'quarantine'::character varying, 'dead'::character varying])::text[])))
);


--
-- Name: whatsapp_phone_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_phone_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_phone_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_phone_profiles_id_seq OWNED BY public.whatsapp_phone_profiles.id;


--
-- Name: whatsapp_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_settings (
    id bigint NOT NULL,
    lock_key character varying(255) NOT NULL,
    hard_fail_threshold smallint DEFAULT '3'::smallint NOT NULL,
    bulk_dead_threshold smallint DEFAULT '10'::smallint NOT NULL,
    no_engagement_threshold smallint DEFAULT '5'::smallint NOT NULL,
    cooldown_no_engagement_days smallint DEFAULT '90'::smallint NOT NULL,
    min_days_between_sends smallint DEFAULT '0'::smallint NOT NULL,
    cooldown_quality_hold_days smallint DEFAULT '3'::smallint NOT NULL,
    cooldown_experiment_days smallint DEFAULT '7'::smallint NOT NULL,
    cooldown_regional_days smallint DEFAULT '30'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    reanalysis_status character varying(20),
    reanalysis_started_at timestamp(0) without time zone,
    reanalysis_completed_at timestamp(0) without time zone,
    last_run_duration_seconds integer
);


--
-- Name: whatsapp_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_settings_id_seq OWNED BY public.whatsapp_settings.id;


--
-- Name: buildings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings ALTER COLUMN id SET DEFAULT nextval('public.buildings_id_seq'::regclass);


--
-- Name: campaign_target_locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_target_locations ALTER COLUMN id SET DEFAULT nextval('public.campaign_target_locations_id_seq'::regclass);


--
-- Name: central_database_exports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.central_database_exports ALTER COLUMN id SET DEFAULT nextval('public.central_database_exports_id_seq'::regclass);


--
-- Name: client_audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_audit_logs ALTER COLUMN id SET DEFAULT nextval('public.client_audit_logs_id_seq'::regclass);


--
-- Name: client_emails id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_emails ALTER COLUMN id SET DEFAULT nextval('public.client_emails_id_seq'::regclass);


--
-- Name: client_interactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_interactions ALTER COLUMN id SET DEFAULT nextval('public.client_interactions_id_seq'::regclass);


--
-- Name: client_phone_numbers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_phone_numbers ALTER COLUMN id SET DEFAULT nextval('public.client_phone_numbers_id_seq'::regclass);


--
-- Name: client_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_sources ALTER COLUMN id SET DEFAULT nextval('public.client_sources_id_seq'::regclass);


--
-- Name: client_tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_tags ALTER COLUMN id SET DEFAULT nextval('public.client_tags_id_seq'::regclass);


--
-- Name: clients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clients ALTER COLUMN id SET DEFAULT nextval('public.clients_id_seq'::regclass);


--
-- Name: contact_suppressions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_suppressions ALTER COLUMN id SET DEFAULT nextval('public.contact_suppressions_id_seq'::regclass);


--
-- Name: countries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries ALTER COLUMN id SET DEFAULT nextval('public.countries_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: import_staging id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staging ALTER COLUMN id SET DEFAULT nextval('public.import_staging_id_seq'::regclass);


--
-- Name: ivr_call_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_call_records ALTER COLUMN id SET DEFAULT nextval('public.ivr_call_records_id_seq'::regclass);


--
-- Name: ivr_campaigns id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_campaigns ALTER COLUMN id SET DEFAULT nextval('public.ivr_campaigns_id_seq'::regclass);


--
-- Name: ivr_import_errors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_import_errors ALTER COLUMN id SET DEFAULT nextval('public.ivr_import_errors_id_seq'::regclass);


--
-- Name: ivr_imports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_imports ALTER COLUMN id SET DEFAULT nextval('public.ivr_imports_id_seq'::regclass);


--
-- Name: ivr_monthly_summaries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_monthly_summaries ALTER COLUMN id SET DEFAULT nextval('public.ivr_monthly_summaries_id_seq'::regclass);


--
-- Name: ivr_phone_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_phone_profiles ALTER COLUMN id SET DEFAULT nextval('public.ivr_phone_profiles_id_seq'::regclass);


--
-- Name: ivr_scripts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_scripts ALTER COLUMN id SET DEFAULT nextval('public.ivr_scripts_id_seq'::regclass);


--
-- Name: ivr_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_settings ALTER COLUMN id SET DEFAULT nextval('public.ivr_settings_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: marketing_area_official_areas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_area_official_areas ALTER COLUMN id SET DEFAULT nextval('public.marketing_area_official_areas_id_seq'::regclass);


--
-- Name: marketing_areas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_areas ALTER COLUMN id SET DEFAULT nextval('public.marketing_areas_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: official_areas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.official_areas ALTER COLUMN id SET DEFAULT nextval('public.official_areas_id_seq'::regclass);


--
-- Name: ownerships id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ownerships ALTER COLUMN id SET DEFAULT nextval('public.ownerships_id_seq'::regclass);


--
-- Name: place_aliases id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.place_aliases ALTER COLUMN id SET DEFAULT nextval('public.place_aliases_id_seq'::regclass);


--
-- Name: projects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects ALTER COLUMN id SET DEFAULT nextval('public.projects_id_seq'::regclass);


--
-- Name: tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags ALTER COLUMN id SET DEFAULT nextval('public.tags_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: whatsapp_campaigns id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_campaigns ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_campaigns_id_seq'::regclass);


--
-- Name: whatsapp_export_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_export_batches ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_export_batches_id_seq'::regclass);


--
-- Name: whatsapp_import_errors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_import_errors ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_import_errors_id_seq'::regclass);


--
-- Name: whatsapp_imports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_imports ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_imports_id_seq'::regclass);


--
-- Name: whatsapp_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_messages ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_messages_id_seq'::regclass);


--
-- Name: whatsapp_monthly_summaries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_monthly_summaries ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_monthly_summaries_id_seq'::regclass);


--
-- Name: whatsapp_phone_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_phone_profiles ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_phone_profiles_id_seq'::regclass);


--
-- Name: whatsapp_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_settings ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_settings_id_seq'::regclass);


--
-- Name: buildings buildings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_pkey PRIMARY KEY (id);


--
-- Name: buildings buildings_project_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_project_id_name_unique UNIQUE (project_id, name);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: campaign_target_locations campaign_target_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_target_locations
    ADD CONSTRAINT campaign_target_locations_pkey PRIMARY KEY (id);


--
-- Name: central_database_exports central_database_exports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.central_database_exports
    ADD CONSTRAINT central_database_exports_pkey PRIMARY KEY (id);


--
-- Name: client_audit_logs client_audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_audit_logs
    ADD CONSTRAINT client_audit_logs_pkey PRIMARY KEY (id);


--
-- Name: client_emails client_emails_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_emails
    ADD CONSTRAINT client_emails_pkey PRIMARY KEY (id);


--
-- Name: client_interactions client_interactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_interactions
    ADD CONSTRAINT client_interactions_pkey PRIMARY KEY (id);


--
-- Name: client_phone_numbers client_phone_numbers_normalized_phone_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_phone_numbers
    ADD CONSTRAINT client_phone_numbers_normalized_phone_unique UNIQUE (normalized_phone);


--
-- Name: client_phone_numbers client_phone_numbers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_phone_numbers
    ADD CONSTRAINT client_phone_numbers_pkey PRIMARY KEY (id);


--
-- Name: client_sources client_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_sources
    ADD CONSTRAINT client_sources_pkey PRIMARY KEY (id);


--
-- Name: client_tags client_tags_client_id_tag_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_tags
    ADD CONSTRAINT client_tags_client_id_tag_id_unique UNIQUE (client_id, tag_id);


--
-- Name: client_tags client_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_tags
    ADD CONSTRAINT client_tags_pkey PRIMARY KEY (id);


--
-- Name: clients clients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_pkey PRIMARY KEY (id);


--
-- Name: contact_suppressions contact_suppressions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_suppressions
    ADD CONSTRAINT contact_suppressions_pkey PRIMARY KEY (id);


--
-- Name: countries countries_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_iso_code_unique UNIQUE (iso_code);


--
-- Name: countries countries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: import_staging import_staging_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staging
    ADD CONSTRAINT import_staging_pkey PRIMARY KEY (id);


--
-- Name: ivr_call_records ivr_call_records_external_call_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_call_records
    ADD CONSTRAINT ivr_call_records_external_call_uuid_unique UNIQUE (external_call_uuid);


--
-- Name: ivr_call_records ivr_call_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_call_records
    ADD CONSTRAINT ivr_call_records_pkey PRIMARY KEY (id);


--
-- Name: ivr_campaigns ivr_campaigns_external_campaign_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_campaigns
    ADD CONSTRAINT ivr_campaigns_external_campaign_id_unique UNIQUE (external_campaign_id);


--
-- Name: ivr_campaigns ivr_campaigns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_campaigns
    ADD CONSTRAINT ivr_campaigns_pkey PRIMARY KEY (id);


--
-- Name: ivr_import_errors ivr_import_errors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_import_errors
    ADD CONSTRAINT ivr_import_errors_pkey PRIMARY KEY (id);


--
-- Name: ivr_imports ivr_imports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_imports
    ADD CONSTRAINT ivr_imports_pkey PRIMARY KEY (id);


--
-- Name: ivr_monthly_summaries ivr_monthly_summaries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_monthly_summaries
    ADD CONSTRAINT ivr_monthly_summaries_pkey PRIMARY KEY (id);


--
-- Name: ivr_monthly_summaries ivr_monthly_summaries_year_month_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_monthly_summaries
    ADD CONSTRAINT ivr_monthly_summaries_year_month_unique UNIQUE (year, month);


--
-- Name: ivr_phone_profiles ivr_phone_profiles_client_phone_number_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_phone_profiles
    ADD CONSTRAINT ivr_phone_profiles_client_phone_number_id_unique UNIQUE (client_phone_number_id);


--
-- Name: ivr_phone_profiles ivr_phone_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_phone_profiles
    ADD CONSTRAINT ivr_phone_profiles_pkey PRIMARY KEY (id);


--
-- Name: ivr_scripts ivr_scripts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_scripts
    ADD CONSTRAINT ivr_scripts_pkey PRIMARY KEY (id);


--
-- Name: ivr_settings ivr_settings_lock_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_settings
    ADD CONSTRAINT ivr_settings_lock_key_unique UNIQUE (lock_key);


--
-- Name: ivr_settings ivr_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_settings
    ADD CONSTRAINT ivr_settings_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: marketing_area_official_areas marketing_area_official_areas_marketing_area_id_official_area_i; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_area_official_areas
    ADD CONSTRAINT marketing_area_official_areas_marketing_area_id_official_area_i UNIQUE (marketing_area_id, official_area_id);


--
-- Name: marketing_area_official_areas marketing_area_official_areas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_area_official_areas
    ADD CONSTRAINT marketing_area_official_areas_pkey PRIMARY KEY (id);


--
-- Name: marketing_areas marketing_areas_emirate_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_areas
    ADD CONSTRAINT marketing_areas_emirate_name_unique UNIQUE (emirate, name);


--
-- Name: marketing_areas marketing_areas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_areas
    ADD CONSTRAINT marketing_areas_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: official_areas official_areas_emirate_area_name_en_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.official_areas
    ADD CONSTRAINT official_areas_emirate_area_name_en_unique UNIQUE (emirate, area_name_en);


--
-- Name: official_areas official_areas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.official_areas
    ADD CONSTRAINT official_areas_pkey PRIMARY KEY (id);


--
-- Name: ownerships ownerships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ownerships
    ADD CONSTRAINT ownerships_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: place_aliases place_aliases_entity_type_entity_id_alias_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.place_aliases
    ADD CONSTRAINT place_aliases_entity_type_entity_id_alias_name_unique UNIQUE (entity_type, entity_id, alias_name);


--
-- Name: place_aliases place_aliases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.place_aliases
    ADD CONSTRAINT place_aliases_pkey PRIMARY KEY (id);


--
-- Name: projects projects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: tags tags_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_name_unique UNIQUE (name);


--
-- Name: tags tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_campaigns whatsapp_campaigns_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_campaigns
    ADD CONSTRAINT whatsapp_campaigns_name_unique UNIQUE (name);


--
-- Name: whatsapp_campaigns whatsapp_campaigns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_campaigns
    ADD CONSTRAINT whatsapp_campaigns_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_export_batch_numbers whatsapp_export_batch_numbers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_export_batch_numbers
    ADD CONSTRAINT whatsapp_export_batch_numbers_pkey PRIMARY KEY (whatsapp_export_batch_id, client_phone_number_id);


--
-- Name: whatsapp_export_batches whatsapp_export_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_export_batches
    ADD CONSTRAINT whatsapp_export_batches_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_import_errors whatsapp_import_errors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_import_errors
    ADD CONSTRAINT whatsapp_import_errors_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_imports whatsapp_imports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_imports
    ADD CONSTRAINT whatsapp_imports_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_messages whatsapp_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_monthly_summaries whatsapp_monthly_summaries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_monthly_summaries
    ADD CONSTRAINT whatsapp_monthly_summaries_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_monthly_summaries whatsapp_monthly_summaries_year_month_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_monthly_summaries
    ADD CONSTRAINT whatsapp_monthly_summaries_year_month_unique UNIQUE (year, month);


--
-- Name: whatsapp_phone_profiles whatsapp_phone_profiles_client_phone_number_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_phone_profiles
    ADD CONSTRAINT whatsapp_phone_profiles_client_phone_number_id_unique UNIQUE (client_phone_number_id);


--
-- Name: whatsapp_phone_profiles whatsapp_phone_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_phone_profiles
    ADD CONSTRAINT whatsapp_phone_profiles_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_settings whatsapp_settings_lock_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_settings
    ADD CONSTRAINT whatsapp_settings_lock_key_unique UNIQUE (lock_key);


--
-- Name: whatsapp_settings whatsapp_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_settings
    ADD CONSTRAINT whatsapp_settings_pkey PRIMARY KEY (id);


--
-- Name: buildings_emirate_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildings_emirate_index ON public.buildings USING btree (emirate);


--
-- Name: buildings_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildings_is_active_index ON public.buildings USING btree (is_active);


--
-- Name: buildings_marketing_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildings_marketing_area_id_index ON public.buildings USING btree (marketing_area_id);


--
-- Name: buildings_official_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildings_official_area_id_index ON public.buildings USING btree (official_area_id);


--
-- Name: buildings_project_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buildings_project_id_index ON public.buildings USING btree (project_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: campaign_target_locations_campaign_id_campaign_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX campaign_target_locations_campaign_id_campaign_type_index ON public.campaign_target_locations USING btree (campaign_id, campaign_type);


--
-- Name: campaign_target_locations_campaign_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX campaign_target_locations_campaign_id_index ON public.campaign_target_locations USING btree (campaign_id);


--
-- Name: campaign_target_locations_emirate_marketing_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX campaign_target_locations_emirate_marketing_area_id_index ON public.campaign_target_locations USING btree (emirate, marketing_area_id);


--
-- Name: central_database_exports_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX central_database_exports_status_index ON public.central_database_exports USING btree (status);


--
-- Name: client_audit_logs_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_audit_logs_client_id_index ON public.client_audit_logs USING btree (client_id);


--
-- Name: client_audit_logs_target_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_audit_logs_target_client_id_index ON public.client_audit_logs USING btree (target_client_id);


--
-- Name: client_emails_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_emails_client_id_index ON public.client_emails USING btree (client_id);


--
-- Name: client_emails_client_lower_email_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX client_emails_client_lower_email_unique ON public.client_emails USING btree (client_id, lower((email)::text));


--
-- Name: client_emails_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_emails_email_index ON public.client_emails USING btree (email);


--
-- Name: client_emails_one_primary_per_client; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX client_emails_one_primary_per_client ON public.client_emails USING btree (client_id) WHERE is_primary;


--
-- Name: client_interactions_client_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_interactions_client_id_created_at_index ON public.client_interactions USING btree (client_id, created_at);


--
-- Name: client_interactions_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_interactions_type_index ON public.client_interactions USING btree (type);


--
-- Name: client_phone_numbers_client_delete_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_client_delete_idx ON public.client_phone_numbers USING btree (client_id);


--
-- Name: client_phone_numbers_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_created_at_index ON public.client_phone_numbers USING btree (created_at);


--
-- Name: client_phone_numbers_is_ivr_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_is_ivr_index ON public.client_phone_numbers USING btree (is_ivr);


--
-- Name: client_phone_numbers_is_primary_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_is_primary_index ON public.client_phone_numbers USING btree (is_primary);


--
-- Name: client_phone_numbers_is_shared_line_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_is_shared_line_index ON public.client_phone_numbers USING btree (is_shared_line);


--
-- Name: client_phone_numbers_is_uae_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_is_uae_index ON public.client_phone_numbers USING btree (is_uae);


--
-- Name: client_phone_numbers_is_whatsapp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_is_whatsapp_index ON public.client_phone_numbers USING btree (is_whatsapp);


--
-- Name: client_phone_numbers_is_whatsapp_lead_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_is_whatsapp_lead_index ON public.client_phone_numbers USING btree (is_whatsapp_lead);


--
-- Name: client_phone_numbers_normalized_phone_trgm_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_normalized_phone_trgm_index ON public.client_phone_numbers USING gin (normalized_phone public.gin_trgm_ops);


--
-- Name: client_phone_numbers_one_primary_per_client; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX client_phone_numbers_one_primary_per_client ON public.client_phone_numbers USING btree (client_id) WHERE is_primary;


--
-- Name: client_phone_numbers_priority_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_priority_index ON public.client_phone_numbers USING btree (priority);


--
-- Name: client_phone_numbers_raw_phone_trgm_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_raw_phone_trgm_index ON public.client_phone_numbers USING gin (raw_phone public.gin_trgm_ops);


--
-- Name: client_phone_numbers_reentered_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_reentered_idx ON public.client_phone_numbers USING btree (reentered_while_suppressed_at) WHERE (reentered_while_suppressed_at IS NOT NULL);


--
-- Name: client_phone_numbers_verification_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_phone_numbers_verification_status_index ON public.client_phone_numbers USING btree (verification_status);


--
-- Name: client_sources_channel_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_sources_channel_index ON public.client_sources USING btree (channel);


--
-- Name: client_sources_client_delete_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_sources_client_delete_idx ON public.client_sources USING btree (client_id);


--
-- Name: client_sources_import_delete_lookup_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_sources_import_delete_lookup_idx ON public.client_sources USING btree (channel, source_type, source_reference);


--
-- Name: client_sources_phone_delete_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_sources_phone_delete_idx ON public.client_sources USING btree (client_phone_number_id);


--
-- Name: client_sources_source_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_sources_source_name_index ON public.client_sources USING btree (source_name);


--
-- Name: client_sources_source_reference_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_sources_source_reference_index ON public.client_sources USING btree (source_reference);


--
-- Name: client_sources_source_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_sources_source_type_index ON public.client_sources USING btree (source_type);


--
-- Name: client_tags_tag_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_tags_tag_id_idx ON public.client_tags USING btree (tag_id);


--
-- Name: clients_completeness_score_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_completeness_score_index ON public.clients USING btree (completeness_score);


--
-- Name: clients_country_iso_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_country_iso_index ON public.clients USING btree (country_iso);


--
-- Name: clients_emirate_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_emirate_index ON public.clients USING btree (emirate);


--
-- Name: clients_full_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_full_name_index ON public.clients USING btree (full_name);


--
-- Name: clients_full_name_trgm_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_full_name_trgm_index ON public.clients USING gin (full_name public.gin_trgm_ops);


--
-- Name: clients_interest_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_interest_index ON public.clients USING btree (interest);


--
-- Name: clients_is_institution_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_is_institution_index ON public.clients USING btree (is_institution);


--
-- Name: clients_tier_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_tier_index ON public.clients USING btree (tier);


--
-- Name: clients_wealth_score_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clients_wealth_score_index ON public.clients USING btree (wealth_score);


--
-- Name: contact_suppressions_channel_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contact_suppressions_channel_index ON public.contact_suppressions USING btree (channel);


--
-- Name: contact_suppressions_lookup_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contact_suppressions_lookup_idx ON public.contact_suppressions USING btree (client_phone_number_id, channel, released_at);


--
-- Name: contact_suppressions_phone_delete_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contact_suppressions_phone_delete_idx ON public.contact_suppressions USING btree (client_phone_number_id);


--
-- Name: contact_suppressions_platform_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contact_suppressions_platform_index ON public.contact_suppressions USING btree (platform);


--
-- Name: contact_suppressions_suppressed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contact_suppressions_suppressed_at_index ON public.contact_suppressions USING btree (suppressed_at);


--
-- Name: import_staging_batch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_staging_batch_id_index ON public.import_staging USING btree (batch_id);


--
-- Name: import_staging_batch_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_staging_batch_id_status_index ON public.import_staging USING btree (batch_id, status);


--
-- Name: import_staging_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_staging_status_index ON public.import_staging USING btree (status);


--
-- Name: ivr_call_records_call_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_call_records_call_status_index ON public.ivr_call_records USING btree (call_status);


--
-- Name: ivr_call_records_call_time_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_call_records_call_time_index ON public.ivr_call_records USING btree (call_time);


--
-- Name: ivr_call_records_campaign_dtmf_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_call_records_campaign_dtmf_idx ON public.ivr_call_records USING btree (ivr_campaign_id, dtmf_outcome);


--
-- Name: ivr_call_records_campaign_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_call_records_campaign_status_idx ON public.ivr_call_records USING btree (ivr_campaign_id, call_status);


--
-- Name: ivr_call_records_client_phone_number_id_call_time_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_call_records_client_phone_number_id_call_time_index ON public.ivr_call_records USING btree (client_phone_number_id, call_time);


--
-- Name: ivr_call_records_dtmf_outcome_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_call_records_dtmf_outcome_index ON public.ivr_call_records USING btree (dtmf_outcome);


--
-- Name: ivr_call_records_import_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_call_records_import_idx ON public.ivr_call_records USING btree (ivr_import_id) WHERE (ivr_import_id IS NOT NULL);


--
-- Name: ivr_import_errors_import_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_import_errors_import_idx ON public.ivr_import_errors USING btree (ivr_import_id);


--
-- Name: ivr_imports_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_imports_status_index ON public.ivr_imports USING btree (status);


--
-- Name: ivr_imports_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_imports_type_index ON public.ivr_imports USING btree (type);


--
-- Name: ivr_imports_type_original_file_name_reverted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ivr_imports_type_original_file_name_reverted_at_index ON public.ivr_imports USING btree (type, original_file_name, reverted_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: marketing_areas_emirate_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX marketing_areas_emirate_index ON public.marketing_areas USING btree (emirate);


--
-- Name: marketing_areas_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX marketing_areas_is_active_index ON public.marketing_areas USING btree (is_active);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: official_areas_emirate_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX official_areas_emirate_index ON public.official_areas USING btree (emirate);


--
-- Name: official_areas_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX official_areas_is_active_index ON public.official_areas USING btree (is_active);


--
-- Name: official_areas_source_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX official_areas_source_area_id_index ON public.official_areas USING btree (source_area_id);


--
-- Name: ownerships_building_id_relationship_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownerships_building_id_relationship_type_index ON public.ownerships USING btree (building_id, relationship_type);


--
-- Name: ownerships_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownerships_client_id_index ON public.ownerships USING btree (client_id);


--
-- Name: ownerships_emirate_marketing_area_id_relationship_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownerships_emirate_marketing_area_id_relationship_type_index ON public.ownerships USING btree (emirate, marketing_area_id, relationship_type);


--
-- Name: ownerships_marketing_area_id_relationship_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownerships_marketing_area_id_relationship_type_index ON public.ownerships USING btree (marketing_area_id, relationship_type);


--
-- Name: ownerships_official_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownerships_official_area_id_index ON public.ownerships USING btree (official_area_id);


--
-- Name: ownerships_project_id_relationship_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownerships_project_id_relationship_type_index ON public.ownerships USING btree (project_id, relationship_type);


--
-- Name: place_aliases_alias_lower_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX place_aliases_alias_lower_idx ON public.place_aliases USING btree (lower((alias_name)::text));


--
-- Name: place_aliases_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX place_aliases_entity_type_entity_id_index ON public.place_aliases USING btree (entity_type, entity_id);


--
-- Name: projects_dld_project_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_dld_project_id_index ON public.projects USING btree (dld_project_id);


--
-- Name: projects_emirate_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_emirate_index ON public.projects USING btree (emirate);


--
-- Name: projects_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_is_active_index ON public.projects USING btree (is_active);


--
-- Name: projects_marketing_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_marketing_area_id_index ON public.projects USING btree (marketing_area_id);


--
-- Name: projects_official_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_official_area_id_index ON public.projects USING btree (official_area_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: whatsapp_export_batch_numbers_client_phone_number_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_export_batch_numbers_client_phone_number_id_index ON public.whatsapp_export_batch_numbers USING btree (client_phone_number_id);


--
-- Name: whatsapp_import_errors_import_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_import_errors_import_idx ON public.whatsapp_import_errors USING btree (whatsapp_import_id);


--
-- Name: whatsapp_imports_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_imports_status_index ON public.whatsapp_imports USING btree (status);


--
-- Name: whatsapp_imports_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_imports_type_index ON public.whatsapp_imports USING btree (type);


--
-- Name: whatsapp_messages_campaign_delivery_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_messages_campaign_delivery_idx ON public.whatsapp_messages USING btree (whatsapp_campaign_id, delivery_status);


--
-- Name: whatsapp_messages_delivery_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_messages_delivery_status_index ON public.whatsapp_messages USING btree (delivery_status);


--
-- Name: whatsapp_messages_phone_scheduled_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_messages_phone_scheduled_idx ON public.whatsapp_messages USING btree (client_phone_number_id, scheduled_at DESC, id DESC) WHERE (client_phone_number_id IS NOT NULL);


--
-- Name: whatsapp_messages_scheduled_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_messages_scheduled_at_index ON public.whatsapp_messages USING btree (scheduled_at);


--
-- Name: whatsapp_messages_whatsapp_campaign_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_messages_whatsapp_campaign_id_index ON public.whatsapp_messages USING btree (whatsapp_campaign_id);


--
-- Name: whatsapp_messages_whatsapp_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_messages_whatsapp_import_id_index ON public.whatsapp_messages USING btree (whatsapp_import_id);


--
-- Name: whatsapp_phone_profiles_manually_dead_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_phone_profiles_manually_dead_index ON public.whatsapp_phone_profiles USING btree (manually_dead);


--
-- Name: whatsapp_phone_profiles_usage_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX whatsapp_phone_profiles_usage_status_index ON public.whatsapp_phone_profiles USING btree (usage_status);


--
-- Name: buildings buildings_marketing_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_marketing_area_id_foreign FOREIGN KEY (marketing_area_id) REFERENCES public.marketing_areas(id) ON DELETE SET NULL;


--
-- Name: buildings buildings_official_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_official_area_id_foreign FOREIGN KEY (official_area_id) REFERENCES public.official_areas(id) ON DELETE SET NULL;


--
-- Name: buildings buildings_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE SET NULL;


--
-- Name: campaign_target_locations campaign_target_locations_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_target_locations
    ADD CONSTRAINT campaign_target_locations_building_id_foreign FOREIGN KEY (building_id) REFERENCES public.buildings(id) ON DELETE SET NULL;


--
-- Name: campaign_target_locations campaign_target_locations_marketing_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_target_locations
    ADD CONSTRAINT campaign_target_locations_marketing_area_id_foreign FOREIGN KEY (marketing_area_id) REFERENCES public.marketing_areas(id) ON DELETE SET NULL;


--
-- Name: campaign_target_locations campaign_target_locations_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_target_locations
    ADD CONSTRAINT campaign_target_locations_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE SET NULL;


--
-- Name: central_database_exports central_database_exports_requested_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.central_database_exports
    ADD CONSTRAINT central_database_exports_requested_by_foreign FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: client_emails client_emails_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_emails
    ADD CONSTRAINT client_emails_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE CASCADE;


--
-- Name: client_interactions client_interactions_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_interactions
    ADD CONSTRAINT client_interactions_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE CASCADE;


--
-- Name: client_phone_numbers client_phone_numbers_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_phone_numbers
    ADD CONSTRAINT client_phone_numbers_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE SET NULL;


--
-- Name: client_sources client_sources_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_sources
    ADD CONSTRAINT client_sources_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE SET NULL;


--
-- Name: client_sources client_sources_client_phone_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_sources
    ADD CONSTRAINT client_sources_client_phone_number_id_foreign FOREIGN KEY (client_phone_number_id) REFERENCES public.client_phone_numbers(id) ON DELETE SET NULL;


--
-- Name: client_tags client_tags_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_tags
    ADD CONSTRAINT client_tags_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE CASCADE;


--
-- Name: client_tags client_tags_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_tags
    ADD CONSTRAINT client_tags_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.tags(id) ON DELETE CASCADE;


--
-- Name: contact_suppressions contact_suppressions_client_phone_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_suppressions
    ADD CONSTRAINT contact_suppressions_client_phone_number_id_foreign FOREIGN KEY (client_phone_number_id) REFERENCES public.client_phone_numbers(id) ON DELETE CASCADE;


--
-- Name: import_staging import_staging_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staging
    ADD CONSTRAINT import_staging_building_id_foreign FOREIGN KEY (building_id) REFERENCES public.buildings(id) ON DELETE SET NULL;


--
-- Name: import_staging import_staging_marketing_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staging
    ADD CONSTRAINT import_staging_marketing_area_id_foreign FOREIGN KEY (marketing_area_id) REFERENCES public.marketing_areas(id) ON DELETE SET NULL;


--
-- Name: import_staging import_staging_official_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staging
    ADD CONSTRAINT import_staging_official_area_id_foreign FOREIGN KEY (official_area_id) REFERENCES public.official_areas(id) ON DELETE SET NULL;


--
-- Name: import_staging import_staging_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staging
    ADD CONSTRAINT import_staging_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE SET NULL;


--
-- Name: ivr_call_records ivr_call_records_client_phone_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_call_records
    ADD CONSTRAINT ivr_call_records_client_phone_number_id_foreign FOREIGN KEY (client_phone_number_id) REFERENCES public.client_phone_numbers(id) ON DELETE CASCADE;


--
-- Name: ivr_call_records ivr_call_records_ivr_campaign_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_call_records
    ADD CONSTRAINT ivr_call_records_ivr_campaign_id_foreign FOREIGN KEY (ivr_campaign_id) REFERENCES public.ivr_campaigns(id) ON DELETE SET NULL;


--
-- Name: ivr_call_records ivr_call_records_ivr_import_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_call_records
    ADD CONSTRAINT ivr_call_records_ivr_import_id_foreign FOREIGN KEY (ivr_import_id) REFERENCES public.ivr_imports(id) ON DELETE SET NULL;


--
-- Name: ivr_campaigns ivr_campaigns_ivr_script_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_campaigns
    ADD CONSTRAINT ivr_campaigns_ivr_script_id_foreign FOREIGN KEY (ivr_script_id) REFERENCES public.ivr_scripts(id) ON DELETE SET NULL;


--
-- Name: ivr_import_errors ivr_import_errors_ivr_import_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_import_errors
    ADD CONSTRAINT ivr_import_errors_ivr_import_id_foreign FOREIGN KEY (ivr_import_id) REFERENCES public.ivr_imports(id) ON DELETE CASCADE;


--
-- Name: ivr_imports ivr_imports_ivr_script_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_imports
    ADD CONSTRAINT ivr_imports_ivr_script_id_foreign FOREIGN KEY (ivr_script_id) REFERENCES public.ivr_scripts(id) ON DELETE SET NULL;


--
-- Name: ivr_imports ivr_imports_reverted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_imports
    ADD CONSTRAINT ivr_imports_reverted_by_foreign FOREIGN KEY (reverted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ivr_imports ivr_imports_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_imports
    ADD CONSTRAINT ivr_imports_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.tags(id) ON DELETE SET NULL;


--
-- Name: ivr_imports ivr_imports_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_imports
    ADD CONSTRAINT ivr_imports_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ivr_phone_profiles ivr_phone_profiles_client_phone_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ivr_phone_profiles
    ADD CONSTRAINT ivr_phone_profiles_client_phone_number_id_foreign FOREIGN KEY (client_phone_number_id) REFERENCES public.client_phone_numbers(id) ON DELETE CASCADE;


--
-- Name: marketing_area_official_areas marketing_area_official_areas_marketing_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_area_official_areas
    ADD CONSTRAINT marketing_area_official_areas_marketing_area_id_foreign FOREIGN KEY (marketing_area_id) REFERENCES public.marketing_areas(id) ON DELETE CASCADE;


--
-- Name: marketing_area_official_areas marketing_area_official_areas_official_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marketing_area_official_areas
    ADD CONSTRAINT marketing_area_official_areas_official_area_id_foreign FOREIGN KEY (official_area_id) REFERENCES public.official_areas(id) ON DELETE CASCADE;


--
-- Name: ownerships ownerships_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ownerships
    ADD CONSTRAINT ownerships_building_id_foreign FOREIGN KEY (building_id) REFERENCES public.buildings(id) ON DELETE SET NULL;


--
-- Name: ownerships ownerships_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ownerships
    ADD CONSTRAINT ownerships_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE CASCADE;


--
-- Name: ownerships ownerships_marketing_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ownerships
    ADD CONSTRAINT ownerships_marketing_area_id_foreign FOREIGN KEY (marketing_area_id) REFERENCES public.marketing_areas(id) ON DELETE SET NULL;


--
-- Name: ownerships ownerships_official_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ownerships
    ADD CONSTRAINT ownerships_official_area_id_foreign FOREIGN KEY (official_area_id) REFERENCES public.official_areas(id) ON DELETE SET NULL;


--
-- Name: ownerships ownerships_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ownerships
    ADD CONSTRAINT ownerships_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE SET NULL;


--
-- Name: projects projects_marketing_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_marketing_area_id_foreign FOREIGN KEY (marketing_area_id) REFERENCES public.marketing_areas(id) ON DELETE SET NULL;


--
-- Name: projects projects_official_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_official_area_id_foreign FOREIGN KEY (official_area_id) REFERENCES public.official_areas(id) ON DELETE SET NULL;


--
-- Name: whatsapp_export_batch_numbers whatsapp_export_batch_numbers_client_phone_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_export_batch_numbers
    ADD CONSTRAINT whatsapp_export_batch_numbers_client_phone_number_id_foreign FOREIGN KEY (client_phone_number_id) REFERENCES public.client_phone_numbers(id) ON DELETE CASCADE;


--
-- Name: whatsapp_export_batch_numbers whatsapp_export_batch_numbers_whatsapp_export_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_export_batch_numbers
    ADD CONSTRAINT whatsapp_export_batch_numbers_whatsapp_export_batch_id_foreign FOREIGN KEY (whatsapp_export_batch_id) REFERENCES public.whatsapp_export_batches(id) ON DELETE CASCADE;


--
-- Name: whatsapp_export_batches whatsapp_export_batches_exported_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_export_batches
    ADD CONSTRAINT whatsapp_export_batches_exported_by_foreign FOREIGN KEY (exported_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: whatsapp_import_errors whatsapp_import_errors_whatsapp_import_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_import_errors
    ADD CONSTRAINT whatsapp_import_errors_whatsapp_import_id_foreign FOREIGN KEY (whatsapp_import_id) REFERENCES public.whatsapp_imports(id) ON DELETE CASCADE;


--
-- Name: whatsapp_imports whatsapp_imports_reverted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_imports
    ADD CONSTRAINT whatsapp_imports_reverted_by_foreign FOREIGN KEY (reverted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: whatsapp_imports whatsapp_imports_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_imports
    ADD CONSTRAINT whatsapp_imports_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: whatsapp_messages whatsapp_messages_client_phone_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_client_phone_number_id_foreign FOREIGN KEY (client_phone_number_id) REFERENCES public.client_phone_numbers(id) ON DELETE SET NULL;


--
-- Name: whatsapp_messages whatsapp_messages_whatsapp_campaign_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_whatsapp_campaign_id_foreign FOREIGN KEY (whatsapp_campaign_id) REFERENCES public.whatsapp_campaigns(id) ON DELETE CASCADE;


--
-- Name: whatsapp_messages whatsapp_messages_whatsapp_import_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_whatsapp_import_id_foreign FOREIGN KEY (whatsapp_import_id) REFERENCES public.whatsapp_imports(id) ON DELETE CASCADE;


--
-- Name: whatsapp_phone_profiles whatsapp_phone_profiles_client_phone_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_phone_profiles
    ADD CONSTRAINT whatsapp_phone_profiles_client_phone_number_id_foreign FOREIGN KEY (client_phone_number_id) REFERENCES public.client_phone_numbers(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict TskTgW3aH7iDUYpr8jLvvK3r9GMjgymHt7eLfLiVH5Pk8CFe5nhNOipFNpo8hcL

--
-- PostgreSQL database dump
--

\restrict FDRwJgU9bCOXkllX3LElj8ZtdprgTpkd4LvFR9mG5pis6nw7usKoOB2JX8IKDb0

-- Dumped from database version 18.3 (Homebrew)
-- Dumped by pg_dump version 18.3 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_04_29_000100_create_clients_table	2
5	2026_04_29_000110_create_client_phone_numbers_table	2
6	2026_04_29_000120_create_client_sources_table	2
7	2026_04_29_000130_create_contact_suppressions_table	2
8	2026_04_29_000200_create_ivr_imports_table	2
9	2026_04_29_000210_create_ivr_import_errors_table	2
10	2026_04_29_000220_create_ivr_campaigns_table	2
11	2026_04_29_000230_create_ivr_call_records_table	2
12	2026_04_29_000240_add_unique_name_to_ivr_campaigns_table	3
13	2026_04_29_000250_add_revert_tracking_to_ivr_imports_table	4
14	2026_05_01_000100_add_contact_preferences_to_client_phone_numbers_table	5
15	2026_05_01_000200_add_interest_to_clients_table	6
16	2026_05_01_000300_add_raw_import_delete_indexes	7
17	2026_05_04_000100_create_ivr_settings_table	8
18	2026_05_04_000200_add_audio_to_ivr_campaigns_and_imports	9
19	2026_05_05_000100_drop_name_unique_from_ivr_campaigns	10
20	2026_05_05_000200_add_lock_key_to_ivr_settings	10
21	2026_05_05_000300_add_check_constraints	11
22	2026_05_05_000400_create_ivr_monthly_summaries_table	11
23	2026_05_06_000100_create_whatsapp_tables	12
24	2026_05_06_000200_create_ivr_phone_profiles_table	13
25	2026_05_06_000300_extract_ivr_columns_from_client_phone_numbers	14
26	2026_05_08_120416_create_central_database_exports_table	15
27	2026_05_12_000100_add_whatsapp_lead_to_client_phone_numbers	16
28	2026_05_12_000200_create_whatsapp_phone_profiles_table	16
29	2026_05_13_000100_add_source_name_to_whatsapp_imports	17
30	2026_05_14_000100_add_cooldown_days_to_ivr_settings	18
31	2026_05_14_000200_create_geography_tables	19
33	2026_05_14_000300_add_geography_fks_to_clients	20
34	2026_05_14_000400_populate_geography_fks_on_clients	20
36	2026_05_14_000500_drop_legacy_location_columns_from_clients	21
37	2026_05_15_000100_upgrade_whatsapp_phone_profiles	22
38	2026_05_16_000100_create_whatsapp_import_errors_table	23
39	2026_05_19_000100_create_ivr_scripts_table	24
40	2026_05_19_000200_add_ivr_script_id_to_ivr_campaigns_and_imports	24
41	2026_06_02_000100_add_composite_indexes_for_campaigns	25
42	2026_06_02_000200_create_whatsapp_monthly_summaries_table	25
43	2026_06_02_000300_update_whatsapp_metrics_add_sent_replied	26
44	2026_06_02_000400_add_column_mapping_to_whatsapp_imports	27
45	2026_06_02_130132_add_lookup_index_to_contact_suppressions	28
46	2026_06_05_000100_create_tags_table	29
47	2026_06_05_000200_create_client_emails_table	30
48	2026_06_05_000300_create_projects_table	31
49	2026_06_05_000400_create_client_interactions_table	32
50	2026_06_05_000500_create_client_communities_table	33
51	2026_06_05_000600_add_dld_fields_to_communities_table	34
52	2026_06_05_000700_migrate_client_email_to_client_emails_table	35
53	2026_06_05_000800_add_contact_lookup_indexes	36
54	2026_06_05_000900_add_contact_integrity_constraints	36
55	2026_06_05_001000_drop_legacy_email_from_clients_table	37
56	2026_06_05_001100_drop_client_phone_primary_unique_index	38
58	2026_06_06_100000_create_official_areas_table	40
59	2026_06_06_100100_create_marketing_areas_table	40
60	2026_06_06_100200_create_place_aliases_table	40
61	2026_06_06_100300_replace_geography_on_clients_and_projects	40
62	2026_06_06_100400_create_buildings_table	40
63	2026_06_06_100500_create_ownerships_table	40
64	2026_06_06_100600_create_import_staging_table	40
65	2026_06_06_100700_create_import_review_queue_table	40
66	2026_06_06_100800_create_campaign_target_locations_table	40
67	2026_06_08_061950_add_tag_id_to_ivr_imports_table	41
68	2026_06_08_120000_normalise_client_emirate_values	42
69	2026_06_09_180000_create_client_activity_timeline_view	43
70	2026_06_09_181000_group_client_activity_timeline_by_campaign	44
71	2026_06_10_134722_add_scoring_to_clients_table	45
72	2026_06_11_100000_add_alternate_names_to_clients	46
73	2026_06_11_110000_add_source_tracking_to_ownerships	47
74	2026_06_11_000100_add_platform_to_whatsapp_campaigns	48
75	2026_06_11_000200_add_original_source_to_clients	49
76	2026_06_11_000300_add_pending_to_whatsapp_delivery_status_check	50
77	2026_06_11_120000_add_unsubscribed_count_to_whatsapp_monthly_summaries	51
78	2026_06_12_000100_add_notes_to_clients	52
79	2026_06_12_100000_create_whatsapp_export_batches_tables	53
80	2026_06_12_110000_create_whatsapp_settings_table	54
81	2026_06_12_120000_add_reanalysis_tracking_to_whatsapp_settings	55
82	2026_06_12_130000_add_last_run_duration_to_whatsapp_settings	56
83	2026_06_12_140000_add_lenient_phones_to_whatsapp_imports	57
84	2026_06_13_000100_normalize_whatsapp_platform_values	58
85	2026_06_13_000200_add_platform_to_contact_suppressions	58
86	2026_06_16_150000_add_placeholder_phone_check_constraint	59
87	2026_06_16_160000_create_client_audit_logs_table	60
88	2026_06_16_170000_add_shared_line_flag_to_client_phone_numbers	61
89	2026_06_17_000100_backfill_phone_verification_status	62
90	2026_06_17_000200_backfill_phone_is_primary	62
91	2026_06_17_000300_add_phone_format_check_constraint	62
92	2026_06_19_000100_add_manually_dead_to_whatsapp_phone_profiles	63
93	2026_06_19_000200_allow_quarantine_usage_status	64
94	2026_06_19_000300_add_is_ivr_to_client_phone_numbers	65
95	2026_06_19_000400_backfill_channel_flags_from_activity	65
96	2026_06_19_000500_add_full_name_index_to_clients	66
97	2026_06_19_000600_add_sort_indexes_for_filament_tables	67
98	2026_06_19_000700_add_trigram_search_indexes	68
99	2026_06_20_000000_drop_import_review_queue_table	69
100	2026_06_20_010000_add_is_institution_to_clients	70
101	2026_06_20_020000_add_reentered_while_suppressed_at_to_client_phone_numbers	71
102	2026_06_20_030000_create_notifications_table	72
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 102, true);


--
-- PostgreSQL database dump complete
--

\unrestrict FDRwJgU9bCOXkllX3LElj8ZtdprgTpkd4LvFR9mG5pis6nw7usKoOB2JX8IKDb0

