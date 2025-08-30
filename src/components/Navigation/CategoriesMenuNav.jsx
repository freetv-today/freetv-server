// src/components/Navigation/CategoriesMenuNav.jsx
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';
import { ButtonCategoryNav } from '@components/Navigation/ButtonCategoryNav';
import { useLocation } from 'preact-iso';
import { capitalizeFirstLetter } from '@/utils';

export function CategoriesMenuNav() {

  const { showData } = useContext(PlaylistContext);
  const { url } = useLocation();

  // Filter out empty categories, get unique categories, and sort alphabetically
  const categories = [
    ...new Set(showData?.filter(item => item.category.trim() !== '').map(item => item.category)),
  ].sort((a, b) => a.localeCompare(b));

  return (
    <div id="mainnav" class="border-bottom border-2 border-dark w-100 p-2 btn-scroll-container text-center">
      {categories.map((category) => (
        <ButtonCategoryNav
          key={category}
          name={capitalizeFirstLetter(category)}
          category={category}
          isActive={url === `/category/${category}`}
        />
      ))}
    </div>
  );
}