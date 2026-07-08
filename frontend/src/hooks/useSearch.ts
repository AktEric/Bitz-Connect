import { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import searchService from '@/services/search.service'

export type SearchTab = 'hotels' | 'jobs' | 'users'

const DEBOUNCE_MS = 400

/**
 * Hook that debounces the search query and fetches results via TanStack Query.
 * Only fires when the debounced query has at least one non-whitespace character.
 * Requirements: 6.1, 6.2, 6.3
 */
export function useSearch(query: string, activeTab: SearchTab, page = 1) {
  const [debouncedQuery, setDebouncedQuery] = useState(query)

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedQuery(query)
    }, DEBOUNCE_MS)

    return () => clearTimeout(timer)
  }, [query])

  const enabled = debouncedQuery.trim().length > 0

  const hotelsQuery = useQuery({
    queryKey: ['search', 'hotels', debouncedQuery, page],
    queryFn: () => searchService.searchHotels(debouncedQuery, page),
    enabled: enabled && activeTab === 'hotels',
  })

  const jobsQuery = useQuery({
    queryKey: ['search', 'jobs', debouncedQuery, page],
    queryFn: () => searchService.searchJobs(debouncedQuery, page),
    enabled: enabled && activeTab === 'jobs',
  })

  const usersQuery = useQuery({
    queryKey: ['search', 'users', debouncedQuery, page],
    queryFn: () => searchService.searchUsers(debouncedQuery, page),
    enabled: enabled && activeTab === 'users',
  })

  const activeQuery = {
    hotels: hotelsQuery,
    jobs: jobsQuery,
    users: usersQuery,
  }[activeTab]

  return {
    debouncedQuery,
    data: activeQuery.data,
    isLoading: activeQuery.isLoading,
    isFetching: activeQuery.isFetching,
    isError: activeQuery.isError,
    error: activeQuery.error,
  }
}
