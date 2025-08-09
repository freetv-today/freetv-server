// src/components/Navigation/CategoriesMenuNav.jsx
import { ButtonCategoryNav } from '@components/Navigation/ButtonCategoryNav';
import { useShowData } from '@/context/ShowDataContext.jsx';
import { useConfig } from '@/context/ConfigContext.jsx';
import { useLocation } from 'preact-iso';
import { capitalizeFirstLetter } from '@/utils.js';

export function CategoriesMenuNav() {
  const { debugmode } = useConfig();
  const showData = useShowData();
  const { url } = useLocation();

  // Filter out empty categories, get unique categories, and sort alphabetically
  const categories = [
    ...new Set(showData?.shows?.filter(item => item.category.trim() !== '').map(item => item.category)),
  ].sort((a, b) => a.localeCompare(b));

  if (debugmode) {
    console.log('showData object:', showData);
    console.log('Number of shows:', showData?.shows?.length);
    console.log('Categories:', categories);
  }

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