import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext.jsx';
import { useAdminSession } from '@/hooks/useAdminSession.js';
import { SearchQueryComponent } from '@components/UI/SearchQueryComponent';
import { ImageLargeLogo } from "@components/UI/ImageLargeLogo";

export function AdminSearch() {
    const { debugmode } = useConfig();
    const user = useAdminSession();

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Search";
        if (debugmode) {
            console.log('Rendered Admin Search page (pages/Admin/search.jsx)');
        }
    }, [debugmode]);

    if (!user) return null;

    return (
      <>
        <h3 class="text-center mt-4">Admin Dashboard Search</h3>
        <SearchQueryComponent onSearch="" />
        <p class="text-center fw-bold mt-5">Search Results Will Appear Here</p>
      </>  
      
    );
}