module.exports = {
  purge: {
    enabled: true,
    content: [
      './resources/**/*.blade.php',
      './resources/**/*.js',
      './app/View/**/*.php',
      './storage/framework/views/*.php',
      './app/Helpers/*.php',
    ],
    options: {
      safelist: [
        // Typography & clamps
        'prose', 'prose-sm', 'prose-lg', 'prose-xl', 'prose-2xl', 'prose-gray', 'prose-invert',
        'line-clamp-1', 'line-clamp-2', 'line-clamp-3', 'line-clamp-4', 'line-clamp-5', 'line-clamp-6',
        
        // Text sizes/weights
        ...Array.from({length:9}).map((_,i)=>`text-${i+1}xl`),
        'font-light', 'font-normal', 'font-medium', 'font-semibold', 'font-bold', 'font-extrabold', 'font-black',
        
        // Grid columns (veel gebruikt in jouw templates)
        ...Array.from({length:12}).map((_,i)=>`grid-cols-${i+1}`),
        ...Array.from({length:12}).map((_,i)=>`sm:grid-cols-${i+1}`),
        ...Array.from({length:12}).map((_,i)=>`md:grid-cols-${i+1}`),
        ...Array.from({length:12}).map((_,i)=>`lg:grid-cols-${i+1}`),
        ...Array.from({length:12}).map((_,i)=>`xl:grid-cols-${i+1}`),
        
        // Spacing
        ...[0,1,2,3,4,5,6,8,10,12,16,20,24,32].flatMap(n => [
          `p-${n}`, `px-${n}`, `py-${n}`, `pt-${n}`, `pr-${n}`, `pb-${n}`, `pl-${n}`,
          `m-${n}`, `mx-${n}`, `my-${n}`, `mt-${n}`, `mr-${n}`, `mb-${n}`, `ml-${n}`,
          `gap-${n}`, `space-x-${n}`, `space-y-${n}`,
        ]),
        
        // Rounded & shadows
        'rounded', 'rounded-sm', 'rounded-md', 'rounded-lg', 'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-full',
        'shadow', 'shadow-sm', 'shadow-md', 'shadow-lg', 'shadow-xl', 'shadow-2xl', 'shadow-inner',
        
        // Flex & display
        'flex', 'inline-flex', 'grid', 'inline-grid', 'block', 'inline-block', 'hidden',
        'items-center', 'items-start', 'items-end', 'items-stretch',
        'justify-center', 'justify-between', 'justify-start', 'justify-end',
        
        // Colors (based on your templates)
        'text-gray-500', 'text-gray-600', 'text-gray-700', 'text-gray-800', 'text-gray-900',
        'bg-gray-50', 'bg-gray-100', 'bg-white', 'bg-black',
        'text-yellow-500', 'bg-yellow-400', 'bg-yellow-500', 'border-yellow-400',
        'text-green-700', 'text-red-500', 'text-blue-600', 'text-blue-700',

        // Gradient colors for top 5 rankings
        'bg-gradient-to-br',
        'from-yellow-400', 'via-yellow-500', 'to-amber-600',
        'from-slate-300', 'via-slate-400', 'to-slate-500',
        'from-orange-500', 'via-orange-600', 'to-orange-700',
        'from-blue-500', 'via-blue-600', 'to-blue-700',
        'from-emerald-500', 'via-emerald-600', 'to-emerald-700',
        'from-purple-500', 'via-purple-600', 'to-purple-700',
        
        // Responsive variants
        ...['sm', 'md', 'lg', 'xl', '2xl'].flatMap(bp => [
          `${bp}:grid`, `${bp}:flex`, `${bp}:hidden`, `${bp}:block`, `${bp}:inline-block`,
          `${bp}:items-center`, `${bp}:justify-between`, `${bp}:text-center`, `${bp}:text-left`,
          `${bp}:p-4`, `${bp}:p-6`, `${bp}:p-8`, `${bp}:px-4`, `${bp}:px-6`, `${bp}:px-8`, `${bp}:px-10`,
        ]),
        
        // Animations
        'animate-ping', 'animate-fade-in', 'animate-fade-in-up', 'animate-slide-up',
      ],
    },
  },
  darkMode: false,
  theme: {
    extend: {},
  },
  variants: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/line-clamp'),
  ],
}
